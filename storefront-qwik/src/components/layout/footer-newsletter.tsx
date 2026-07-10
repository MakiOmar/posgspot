import { component$, useSignal } from "@builder.io/qwik";
import { TurnstileWidget } from "~/components/forms/turnstile-widget";
import { ApiError, subscribeNewsletter } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import type { StoreSettings } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";

interface FooterNewsletterProps {
  settings: StoreSettings;
}

/** Footer email signup — shown when newsletter provider is configured in POS settings. */
export const FooterNewsletter = component$<FooterNewsletterProps>(({ settings }) => {
  const { locale } = useI18n();
  const pending = usePendingState();
  const email = useSignal("");
  const submitting = useSignal(false);
  const turnstileToken = useSignal("");
  const turnstileResetKey = useSignal(0);

  const enabled = Boolean(settings.newsletter?.enabled);
  const turnstile = settings.turnstile;
  const turnstileEnabled = Boolean(turnstile?.enabled && turnstile.site_key);

  if (!enabled) {
    return null;
  }

  return (
    <div class="footer-newsletter">
      <h3>{tStatic(locale, "footer.newsletterTitle")}</h3>
      <p class="footer-muted">{tStatic(locale, "footer.newsletterBlurb")}</p>
      <form
        class="footer-newsletter__form"
        preventdefault:submit
        onSubmit$={async () => {
          const value = email.value.trim();
          if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            await toastError(tStatic(locale, "footer.newsletterInvalidEmail"));
            return;
          }
          if (turnstileEnabled && !turnstileToken.value) {
            await toastError(tStatic(locale, "turnstile.required"));
            return;
          }

          await withPendingFeedback(pending, submitting, async () => {
            try {
              const { data } = await subscribeNewsletter({
                email: value,
                ...(turnstileEnabled ? { turnstile_token: turnstileToken.value } : {}),
              });
              await toastSuccess(data.message);
              email.value = "";
              turnstileResetKey.value += 1;
            } catch (err) {
              const message =
                err instanceof ApiError
                  ? err.message
                  : tStatic(locale, "footer.newsletterFailed");
              await toastError(message);
              turnstileResetKey.value += 1;
            }
          });
        }}
      >
        <label class="sr-only" for="footer-newsletter-email">
          {tStatic(locale, "footer.newsletterEmailLabel")}
        </label>
        <input
          id="footer-newsletter-email"
          type="email"
          name="email"
          autocomplete="email"
          required
          placeholder={tStatic(locale, "footer.newsletterPlaceholder")}
          value={email.value}
          onInput$={(e) => {
            email.value = (e.target as HTMLInputElement).value;
          }}
          disabled={submitting.value}
        />
        <button type="submit" class="btn btn-primary" disabled={submitting.value}>
          {submitting.value
            ? tStatic(locale, "footer.newsletterSubmitting")
            : tStatic(locale, "footer.newsletterSubscribe")}
        </button>
      </form>
      {turnstileEnabled && turnstile.site_key ? (
        <TurnstileWidget
          siteKey={turnstile.site_key}
          token={turnstileToken}
          resetKey={turnstileResetKey.value}
        />
      ) : null}
    </div>
  );
});
