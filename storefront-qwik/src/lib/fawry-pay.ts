import type { FawryPaymentSession } from "~/lib/types";

declare global {
  interface Window {
    FawryPay?: {
      checkout: (chargeRequest: Record<string, unknown>, configuration: Record<string, unknown>) => void;
    };
    DISPLAY_MODE?: {
      SEPARATED: string;
      POPUP: string;
      INSIDE_PAGE: string;
      SIDE_PAGE: string;
    };
  }
}

let sdkPromise: Promise<void> | null = null;

export function loadFawrySdk(url: string): Promise<void> {
  if (typeof window === "undefined") {
    return Promise.resolve();
  }

  if (window.FawryPay?.checkout) {
    return Promise.resolve();
  }

  if (sdkPromise) {
    return sdkPromise;
  }

  sdkPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[data-fawry-sdk="1"]`);
    if (existing) {
      existing.addEventListener("load", () => resolve(), { once: true });
      existing.addEventListener("error", () => reject(new Error("Fawry SDK failed to load")), { once: true });
      return;
    }

    const script = document.createElement("script");
    script.src = url;
    script.async = true;
    script.dataset.fawrySdk = "1";
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Fawry SDK failed to load"));
    document.head.appendChild(script);
  });

  return sdkPromise;
}

export async function startFawryCheckout(session: FawryPaymentSession): Promise<void> {
  await loadFawrySdk(session.sdk_url);

  if (!window.FawryPay?.checkout) {
    throw new Error("FawryPay checkout is unavailable.");
  }

  const chargeRequest = {
    merchantCode: session.charge.merchantCode,
    merchantRefNum: session.charge.merchantRefNum,
    customerProfileId: session.charge.customerProfileId,
    customerMobile: session.charge.customerMobile,
    customerEmail: session.charge.customerEmail,
    customerName: session.charge.customerName,
    chargeItems: session.charge.chargeItems,
    paymentExpiry: session.charge.paymentExpiry,
    returnUrl: session.charge.returnUrl,
    signature: session.charge.signature,
  };

  const configuration = {
    locale: session.locale === "ar" ? "ar" : "en",
    mode: window.DISPLAY_MODE?.SEPARATED ?? "SEPARATED",
  };

  window.FawryPay.checkout(chargeRequest, configuration);
}

export const FAWRY_PAYMENT_STORAGE_KEY = "storefront_fawry_payment";

export function storeFawryPaymentSession(session: FawryPaymentSession): void {
  if (typeof sessionStorage === "undefined") {
    return;
  }
  sessionStorage.setItem(FAWRY_PAYMENT_STORAGE_KEY, JSON.stringify(session));
}

export function readFawryPaymentSession(): FawryPaymentSession | null {
  if (typeof sessionStorage === "undefined") {
    return null;
  }
  const raw = sessionStorage.getItem(FAWRY_PAYMENT_STORAGE_KEY);
  if (!raw) {
    return null;
  }
  try {
    return JSON.parse(raw) as FawryPaymentSession;
  } catch {
    return null;
  }
}

export function clearFawryPaymentSession(): void {
  if (typeof sessionStorage === "undefined") {
    return;
  }
  sessionStorage.removeItem(FAWRY_PAYMENT_STORAGE_KEY);
}
