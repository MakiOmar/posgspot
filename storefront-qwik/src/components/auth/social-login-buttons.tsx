import { $, component$ } from "@builder.io/qwik";
import {
  ApiError,
  fetchSocialRedirectUrl,
  type SocialProvider,
} from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { toastError } from "~/lib/notify";
import { useSiteSettings } from "~/routes/[lang]/layout";

interface Props {
  intent?: "login" | "link";
  next?: string;
}

/**
 * Google / Facebook buttons — starts OAuth via API (JSON url) then navigates.
 */
export const SocialLoginButtons = component$<Props>(({ intent = "login", next }) => {
  const settings = useSiteSettings();
  const auth = useAuth();
  const { locale } = useI18n();
  const google = !!settings.value.social_login?.google_enabled;
  const facebook = !!settings.value.social_login?.facebook_enabled;

  if (!google && !facebook) {
    return null;
  }

  const start$ = $(async (provider: SocialProvider) => {
    try {
      const { data } = await fetchSocialRedirectUrl(provider, {
        intent,
        locale,
        next,
        token: intent === "link" ? auth.token : null,
      });
      if (!data.url) {
        await toastError(tStatic(locale, "auth.socialFailed"));
        return;
      }
      window.location.assign(data.url);
    } catch (e) {
      await toastError(
        e instanceof ApiError ? e.message : tStatic(locale, "auth.socialFailed"),
      );
    }
  });

  return (
    <div class="social-login">
      <p class="social-login__divider">
        <span>{tStatic(locale, "auth.orContinueWith")}</span>
      </p>
      <div class="social-login__buttons">
        {google ? (
          <button
            type="button"
            class="btn btn-secondary social-login__btn"
            onClick$={() => start$("google")}
          >
            {tStatic(locale, "auth.continueGoogle")}
          </button>
        ) : null}
        {facebook ? (
          <button
            type="button"
            class="btn btn-secondary social-login__btn"
            onClick$={() => start$("facebook")}
          >
            {tStatic(locale, "auth.continueFacebook")}
          </button>
        ) : null}
      </div>
    </div>
  );
});
