import type { GeideaPaymentSession } from "~/lib/types";

declare global {
  interface Window {
    GeideaCheckout?: new (
      onSuccess: () => void,
      onError: (error: { responseMessage?: string }) => void,
      onCancel: () => void,
    ) => {
      startPayment: (sessionId: string, _unused?: unknown, containerId?: string) => void;
    };
  }
}

let sdkPromise: Promise<void> | null = null;
let expiryTimer: ReturnType<typeof setInterval> | null = null;

export function loadGeideaSdk(url: string): Promise<void> {
  if (typeof window === "undefined") {
    return Promise.resolve();
  }

  if (window.GeideaCheckout) {
    return Promise.resolve();
  }

  if (sdkPromise) {
    return sdkPromise;
  }

  sdkPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[data-geidea-sdk="1"]`);
    if (existing) {
      existing.addEventListener("load", () => resolve(), { once: true });
      existing.addEventListener("error", () => reject(new Error("Geidea SDK failed to load")), { once: true });
      return;
    }

    const script = document.createElement("script");
    script.src = url;
    // Vendor requires the global before init — no async/defer.
    script.async = false;
    script.dataset.geideaSdk = "1";
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Geidea SDK failed to load"));
    document.head.appendChild(script);
  });

  return sdkPromise;
}

export function stopGeideaExpiryWatcher(): void {
  if (expiryTimer) {
    clearInterval(expiryTimer);
    expiryTimer = null;
  }
}

/**
 * Launch HPP. `onExpired` fires when the drop-in iframe disappears so the
 * caller can request a fresh session (Geidea sessions are short-lived).
 */
export async function startGeideaCheckout(
  session: GeideaPaymentSession,
  handlers: {
    onSuccess: () => void;
    onError: (message: string) => void;
    onCancel: () => void;
    onExpired?: () => void;
  },
): Promise<void> {
  await loadGeideaSdk(session.sdk_url);

  if (!window.GeideaCheckout) {
    throw new Error("Geidea checkout is unavailable.");
  }

  const containerId = session.ui_mode === "dropin" ? session.container_id || "geidea-dropin-container" : undefined;
  if (containerId && typeof document !== "undefined" && !document.getElementById(containerId)) {
    const mount = document.createElement("div");
    mount.id = containerId;
    document.body.appendChild(mount);
  }

  stopGeideaExpiryWatcher();

  const api = new window.GeideaCheckout(
    () => {
      stopGeideaExpiryWatcher();
      handlers.onSuccess();
    },
    (error) => {
      stopGeideaExpiryWatcher();
      handlers.onError(error?.responseMessage || "Payment error");
    },
    () => {
      stopGeideaExpiryWatcher();
      handlers.onCancel();
    },
  );

  if (containerId) {
    api.startPayment(session.session_id, null, containerId);
    expiryTimer = setInterval(() => {
      const container = document.getElementById(containerId);
      const iframe = container?.querySelector("iframe");
      if (container && !iframe && container.innerHTML.trim() === "") {
        stopGeideaExpiryWatcher();
        handlers.onExpired?.();
      }
    }, 2000);
  } else {
    api.startPayment(session.session_id);
  }
}
