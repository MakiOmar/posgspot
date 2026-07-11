import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import {
  loadCookieConsent,
  persistCookieConsent,
  type CookieConsentStatus,
} from "~/lib/cookie-consent";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";

/**
 * Fixed bottom consent bar. Client-only — hidden until hydrated, then shown
 * only when no prior choice exists in localStorage.
 */
export const CookieConsentBanner = component$(() => {
  const { locale } = useI18n();
  const visible = useSignal(false);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(() => {
    visible.value = loadCookieConsent() === null;
  });

  const choose$ = $((status: CookieConsentStatus) => {
    persistCookieConsent(status);
    visible.value = false;
  });

  if (!visible.value) {
    return null;
  }

  return (
    <div class="cookie-consent" role="dialog" aria-labelledby="cookie-consent-title" aria-live="polite">
      <div class="cookie-consent__inner">
        <div class="cookie-consent__copy">
          <p id="cookie-consent-title" class="cookie-consent__title">
            {tStatic(locale, "consent.title")}
          </p>
          <p class="cookie-consent__body">
            {tStatic(locale, "consent.body")}{" "}
            <Link href={localePath(locale, "/privacy-policy")} class="cookie-consent__link">
              {tStatic(locale, "footer.privacy")}
            </Link>
            .
          </p>
        </div>
        <div class="cookie-consent__actions">
          <button
            type="button"
            class="btn btn-secondary cookie-consent__btn"
            onClick$={() => choose$("necessary")}
          >
            {tStatic(locale, "consent.necessaryOnly")}
          </button>
          <button
            type="button"
            class="btn btn-primary cookie-consent__btn"
            onClick$={() => choose$("accepted")}
          >
            {tStatic(locale, "consent.acceptAll")}
          </button>
        </div>
      </div>
    </div>
  );
});
