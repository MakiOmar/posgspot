/**
 * Geidea checkout launcher.
 *
 * Prefers native `@geidea/payment-sdk-react-native` (vendored tarball) via
 * `payWithGeidea` on iOS and Android. Android bridge uses BottomSheet (not Push)
 * so Expo `singleTask` MainActivity does not clear a separate payment activity
 * mid-checkout. Falls back to hosted HPP in a WebView when the native module
 * is missing (Expo Go / incomplete native rebuild).
 *
 * If the JS runtime still remounts, payment screens persist `gs-pending-payment-v1`
 * (with a short-lived auth snapshot) and AppContext restores the session.
 *
 * Fulfilment is always the Laravel webhook (`callbackUrl` on Create Session) —
 * this module only reports UX events. Keep `POST /payments/geidea/webhook`.
 */
import type { GeideaPaymentSession } from "./types";

export type GeideaLaunchResult =
  | { ok: true; event: "completed"; payload?: unknown; orderId?: string }
  | { ok: false; event: "canceled" | "failed"; reason: string; payload?: unknown };

type GeideaNativeEnvironment = "production" | "sandbox";
type GeideaNativeRegion = "egypt" | "ksa" | "uae";
type GeideaNativeLanguage = "en" | "ar";

type PayWithGeideaFn = (options: {
  sessionId: string;
  language?: GeideaNativeLanguage;
  environment?: GeideaNativeEnvironment;
  region?: GeideaNativeRegion;
  merchantId?: string;
  merchantName?: string;
  primaryColor?: string;
  secondaryColor?: string;
}) => Promise<{
  status?: string;
  result?: { orderId?: string; [key: string]: unknown };
  [key: string]: unknown;
}>;

function loadNativeSdk(): { payWithGeidea: PayWithGeideaFn } | null {
  try {
    // Dynamic require so Metro still resolves when the package is present but
    // the native binary is not linked yet (Expo Go / web).
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const mod = require("@geidea/payment-sdk-react-native");
    if (mod?.payWithGeidea) {
      return mod;
    }
  } catch {
    // SDK not installed / not linked
  }
  return null;
}

export function isGeideaNativeSdkAvailable(): boolean {
  return loadNativeSdk() !== null;
}

/** Map Laravel session environment (`prod`/`test`) to SDK 0.0.12 enums. */
export function mapGeideaNativeEnvironment(
  environment: string | undefined,
): GeideaNativeEnvironment {
  const raw = (environment || "").toLowerCase();
  if (raw === "prod" || raw === "production" || raw === "live") {
    return "production";
  }
  return "sandbox";
}

export function mapGeideaNativeRegion(region: string | undefined): GeideaNativeRegion {
  const raw = (region || "egypt").toLowerCase();
  if (raw === "ksa" || raw === "ksa-prod") {
    return "ksa";
  }
  if (raw === "uae" || raw === "uae-prod") {
    return "uae";
  }
  return "egypt";
}

export function mapGeideaNativeLanguage(locale: string | undefined): GeideaNativeLanguage {
  return (locale || "en").toLowerCase().startsWith("ar") ? "ar" : "en";
}

/**
 * Hosted HPP HTML. Script must load without async/defer (vendor requirement).
 * Posts { type: completed|canceled|failed } to React Native via postMessage.
 */
const GEIDEA_SDK_HOST_RE =
  /^https:\/\/([a-z0-9-]+\.)*(geidea\.net|merchant\.geidea\.net)(:\d+)?(\/|$)/i;

export function isAllowedGeideaSdkUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    if (parsed.protocol !== "https:") {
      return false;
    }
    return GEIDEA_SDK_HOST_RE.test(parsed.href);
  } catch {
    return false;
  }
}

export function buildGeideaHostedHtml(session: GeideaPaymentSession): string {
  const rawSdk = String(session.sdk_url || "");
  if (!isAllowedGeideaSdkUrl(rawSdk)) {
    throw new Error("Geidea SDK URL is not allowlisted.");
  }
  const sdkUrl = JSON.stringify(rawSdk);
  const sessionId = JSON.stringify(session.session_id || "");
  const containerId = JSON.stringify(session.container_id || "geidea-dropin-container");
  const dropin = session.ui_mode === "dropin";

  return `<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- Geidea HPP: global must exist before startPayment; no async/defer. -->
    <script src=${sdkUrl}></script>
    <style>
      html, body { margin: 0; padding: 0; min-height: 100%; background: #fff; }
      #geidea-dropin-container { min-height: 100vh; }
    </style>
  </head>
  <body>
    <!-- Drop-in mount used when ui_mode is dropin. -->
    <div id="geidea-dropin-container"></div>
    <script>
      (function () {
        function send(type, message) {
          try {
            if (window.ReactNativeWebView) {
              window.ReactNativeWebView.postMessage(JSON.stringify({
                type: type,
                message: message || ""
              }));
            }
          } catch (e) {}
        }
        if (typeof GeideaCheckout !== "function") {
          send("failed", "Geidea checkout is unavailable.");
          return;
        }
        var sessionId = ${sessionId};
        var containerId = ${containerId};
        var api = new GeideaCheckout(
          function () { send("completed"); },
          function (err) { send("failed", (err && err.message) || String(err || "failed")); },
          function () { send("canceled"); }
        );
        if (${dropin ? "true" : "false"} && containerId) {
          api.startPayment(sessionId, null, containerId);
        } else {
          api.startPayment(sessionId);
        }
      })();
    </script>
  </body>
</html>`;
}

export function parseGeideaWebViewMessage(raw: string): GeideaLaunchResult | null {
  try {
    const parsed = JSON.parse(raw) as { type?: string; message?: string };
    if (parsed.type === "completed") {
      return { ok: true, event: "completed" };
    }
    if (parsed.type === "canceled") {
      return { ok: false, event: "canceled", reason: "canceled" };
    }
    if (parsed.type === "failed") {
      return { ok: false, event: "failed", reason: parsed.message || "failed" };
    }
  } catch {
    return null;
  }
  return null;
}

/**
 * Native SDK path (0.0.12+). Returns null when the package is not linked so
 * callers can fall back to the hosted WebView without changing the screen.
 */
export async function tryNativeGeideaPayment(
  session: GeideaPaymentSession,
): Promise<GeideaLaunchResult | null> {
  const sdk = loadNativeSdk();
  if (!sdk) {
    return null;
  }

  if (!session.session_id) {
    return { ok: false, event: "failed", reason: "Missing Geidea session id" };
  }

  try {
    const result = await sdk.payWithGeidea({
      sessionId: session.session_id,
      language: mapGeideaNativeLanguage(session.locale),
      environment: mapGeideaNativeEnvironment(session.environment),
      region: mapGeideaNativeRegion(session.region),
    });
    const status = String(result?.status || "").toLowerCase();
    const orderId =
      typeof result?.result?.orderId === "string" ? result.result.orderId : undefined;

    if (status === "completed" || status === "success") {
      return { ok: true, event: "completed", payload: result, orderId };
    }
    if (status === "canceled" || status === "cancelled") {
      return { ok: false, event: "canceled", reason: status, payload: result };
    }
    return { ok: false, event: "failed", reason: status || "failed", payload: result };
  } catch (e) {
    return {
      ok: false,
      event: "failed",
      reason: e instanceof Error ? e.message : "payWithGeidea failed",
    };
  }
}

/**
 * Native `payWithGeidea` when the vendored SDK is linked; otherwise hosted HTML.
 */
export async function startGeideaPayment(
  session: GeideaPaymentSession,
): Promise<GeideaLaunchResult | { hostedHtml: string }> {
  const native = await tryNativeGeideaPayment(session);
  if (native) {
    return native;
  }

  return { hostedHtml: buildGeideaHostedHtml(session) };
}
