import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { MailIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface ProtectedEmailLinkProps {
  /** Base64-encoded address from the Storefront settings API. */
  emailEncoded: string;
}

/**
 * Renders a mailto link only after client hydration so the raw address never
 * appears in SSR HTML (see storefront-email-obfuscation.mdc).
 */
export const ProtectedEmailLink = component$<ProtectedEmailLinkProps>(({ emailEncoded }) => {
  const { locale } = useI18n();
  const email = useSignal("");

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(() => {
    if (!emailEncoded) {
      return;
    }
    try {
      email.value = atob(emailEncoded);
    } catch {
      // Ignore corrupt encoding.
    }
  });

  if (!emailEncoded) {
    return null;
  }

  return (
    <p class="footer-muted footer-contact">
      <MailIcon size={16} />
      {email.value ? (
        <a href={`mailto:${email.value}`}>{email.value}</a>
      ) : (
        <span>{tStatic(locale, "contact.emailUs")}</span>
      )}
    </p>
  );
});
