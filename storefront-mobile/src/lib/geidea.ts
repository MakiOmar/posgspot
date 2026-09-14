/**
 * Geidea checkout launcher.
 *
 * Hosted HPP in a WebView ships now. Native `payWithGeidea` is used only when
 * `@geidea/payment-sdk-react-native` is installed (gated on the vendor tarball).
 * Fulfilment is always the Laravel webhook — this module only reports UX events.
 */
import type { GeideaPaymentSession } from "./types";

export type GeideaLaunchResult =
  | { ok: true; event: "completed"; payload?: unknown }
  | { ok: false; event: "canceled" | "failed"; reason: string; payload?: unknown };

type PayWithGeideaFn = (options: Record<string, unknown>) => Promise<{
  status?: string;
  [key: string]: unknown;
}>;

function loadNativeSdk(): { payWithGeidea: PayWithGeideaFn } | null {
  try {
    // Dynamic require so Expo Go / web still bundle without the native module.
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

/**
 * Hosted HPP HTML. Script must load without async/defer (vendor requirement).
 * Posts { type: completed|canceled|failed } to React Native via postMessage.
 */
export function buildGeideaHostedHtml(session: GeideaPaymentSession): string {
  const sdkUrl = JSON.stringify(session.sdk_url || "");
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
          function (err) { send("failed", (err && err.responseMessage) || "Payment error"); },
          function () { send("canceled"); }
        );
        ${dropin ? "api.startPayment(sessionId, null, containerId);" : "api.startPayment(sessionId);"}
      })();
    </script>
  </body>
</html>`;
}

export function parseGeideaWebViewMessage(raw: string): GeideaLaunchResult | null {
  try {
    const parsed = JSON.parse(raw) as { type?: string; message?: string };
    if (parsed.type === "completed") {
      return { ok: true, event: "completed", payload: parsed };
    }
    if (parsed.type === "canceled") {
      return { ok: false, event: "canceled", reason: parsed.message || "canceled" };
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
 * Native SDK path. Returns null when the tarball is not installed so callers
 * can fall back to the hosted WebView without changing the checkout screen.
 */
export async function tryNativeGeideaPayment(
  session: GeideaPaymentSession,
): Promise<GeideaLaunchResult | null> {
  const sdk = loadNativeSdk();
  if (!sdk) {
    return null;
  }

  try {
    const result = await sdk.payWithGeidea({
      sessionId: session.session_id,
      language: session.locale === "ar" ? "AR" : "EN",
      environment: session.environment,
      region: session.region,
    });
    const status = String(result?.status || "").toLowerCase();
    if (status === "completed" || status === "success") {
      return { ok: true, event: "completed", payload: result };
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
 * Native SDK when installed; otherwise return hosted HTML for a WebView.
 * Checkout screens should not branch on the SDK being present.
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
