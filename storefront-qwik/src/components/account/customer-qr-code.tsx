import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { buildCustomerContactVCard } from "~/lib/customer-contact-vcard";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface CustomerQrCodeProps {
  name: string;
  email: string | null;
  mobile: string | null;
}

/**
 * Renders a QR code with the customer's basic contact details (vCard).
 * Generated client-side only so PII is not embedded in SSR HTML.
 */
export const CustomerQrCode = component$<CustomerQrCodeProps>(({ name, email, mobile }) => {
  const { locale } = useI18n();
  const dataUrl = useSignal<string | null>(null);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => name);
    track(() => email);
    track(() => mobile);

    const payload = buildCustomerContactVCard(name, email, mobile);
    const hasContent = payload.includes("FN:") || payload.includes("EMAIL:") || payload.includes("TEL:");

    if (!hasContent) {
      dataUrl.value = null;
      return;
    }

    try {
      const QRCode = (await import("qrcode")).default;
      dataUrl.value = await QRCode.toDataURL(payload, {
        errorCorrectionLevel: "M",
        margin: 2,
        width: 200,
        color: { dark: "#000000", light: "#ffffff" },
      });
    } catch {
      dataUrl.value = null;
    }
  });

  const hasAnyDetail = Boolean(name.trim() || email?.trim() || mobile?.trim());

  if (!hasAnyDetail) {
    return null;
  }

  return (
    <div class="account-qr">
      <h2>{tStatic(locale, "account.qrTitle")}</h2>
      <p class="footer-muted account-qr-hint">{tStatic(locale, "account.qrHint")}</p>
      <div class="account-qr-frame" aria-hidden={!dataUrl.value}>
        {dataUrl.value ? (
          <img
            src={dataUrl.value}
            alt={tStatic(locale, "account.qrAlt")}
            width={200}
            height={200}
            class="account-qr-image"
          />
        ) : (
          <span class="account-qr-placeholder footer-muted">
            {tStatic(locale, "account.generating")}
          </span>
        )}
      </div>
    </div>
  );
});
