/**
 * Fawry Pay RN SDK wrapper.
 *
 * Install + native link (Dev Client / prebuild required):
 *   npm install @fawry_pay/rn-fawry-pay-sdk react-native-nitro-modules
 *
 * Never put merchantSecretCode in the app — use server-signed session fields.
 */
import type { FawryPaymentSession } from "./types";

export type FawryLaunchResult =
  | { ok: true; event: string; payload: unknown }
  | { ok: false; reason: string; payload?: unknown };

type StartPaymentFn = (model: Record<string, unknown>) => void;
type AddListenerFn = (
  listener: (eventName: string, payload: string) => void,
) => () => void;

function loadSdk():
  | { startPayment: StartPaymentFn; addFawryListener: AddListenerFn }
  | null {
  try {
    // Dynamic require so Expo Go / web still bundle without the native module.
    // eslint-disable-next-line @typescript-eslint/no-require-imports
    const mod = require("@fawry_pay/rn-fawry-pay-sdk");
    if (mod?.startPayment && mod?.addFawryListener) {
      return mod;
    }
  } catch {
    // SDK not installed / not linked
  }
  return null;
}

/**
 * Map Laravel checkout `payment` block into FawryLaunchModel fields.
 * Prefer charge + signature from the server; omit secrets.
 */
export function sessionToLaunchModel(
  session: FawryPaymentSession,
  customer: {
    customerName?: string;
    customerMobile?: string;
    customerEmail?: string;
    customerProfileId?: string;
  },
  items: Array<{
    itemId: string;
    description?: string;
    quantity: string;
    price: string;
  }>,
): Record<string, unknown> {
  const charge = (session.charge || {}) as Record<string, unknown>;
  const baseUrl =
    (session.base_url as string | undefined) ||
    (typeof charge.baseUrl === "string" ? charge.baseUrl : undefined) ||
    "https://atfawry.com/";

  return {
    baseUrl,
    lang: session.locale === "ar" ? "ARABIC" : "ENGLISH",
    signature: session.signature || charge.signature || "",
    allow3DPayment: true,
    skipReceipt: false,
    skipLogin: true,
    payWithCardToken: false,
    authCaptureMode: false,
    allowVoucher: true,
    items,
    merchantInfo: {
      merchantCode: session.merchant_code || charge.merchantCode || "",
      merchantRefNum:
        session.merchant_ref_num || charge.merchantRefNum || "",
      // Secret must never be shipped — empty string; server signature used when supported.
      merchantSecretCode: "",
    },
    customerInfo: customer,
    paymentSignature: session.signature || charge.signature || "",
  };
}

export async function startFawryPayment(
  model: Record<string, unknown>,
): Promise<FawryLaunchResult> {
  const sdk = loadSdk();
  if (!sdk) {
    return {
      ok: false,
      reason:
        "Fawry SDK not linked. Use Expo Dev Client / prebuild with @fawry_pay/rn-fawry-pay-sdk.",
    };
  }

  return new Promise((resolve) => {
    const remove = sdk.addFawryListener((eventName, payload) => {
      let parsed: unknown = payload;
      try {
        parsed = JSON.parse(payload);
      } catch {
        // keep string
      }
      if (
        eventName.includes("SUCCESS") ||
        eventName.includes("PAYMENT_COMPLETED")
      ) {
        remove();
        resolve({ ok: true, event: eventName, payload: parsed });
      } else if (eventName.includes("FAIL")) {
        remove();
        resolve({ ok: false, reason: eventName, payload: parsed });
      }
    });

    try {
      sdk.startPayment(model);
    } catch (e) {
      remove();
      resolve({
        ok: false,
        reason: e instanceof Error ? e.message : "startPayment failed",
      });
    }
  });
}

export function isFawrySdkAvailable(): boolean {
  return loadSdk() !== null;
}
