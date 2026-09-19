import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { DialCodeSelect } from "~/components/forms/dial-code-select";
import { TurnstileWidget } from "~/components/forms/turnstile-widget";
import { ApiError, submitCommunityApplication } from "~/lib/api";
import { isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { validatePhone, type PhoneCountry } from "~/lib/phone-validation";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/[lang]/layout";

type Props = {
  slug: string;
  registrationDetails?: string | null;
  phoneCountries: PhoneCountry[];
};

/** Internal registration form (name + mobile) for tournament/event detail. */
export const CommunityRegistrationForm = component$<Props>((props) => {
  const { locale } = useI18n();
  const settings = useSiteSettings();
  const auth = useAuth();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const turnstileToken = useSignal("");
  const turnstileResetKey = useSignal(0);
  const turnstile = settings.value.turnstile;
  const turnstileEnabled = Boolean(turnstile?.enabled && turnstile.site_key);

  const form = useStore({
    name: "",
    dialCode: "+20",
    nationalNumber: "",
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.contact);
    if (!auth.ready || !auth.contact || !isAuthenticated(auth)) {
      return;
    }
    if (!form.name) {
      form.name =
        auth.contact.name ||
        [auth.contact.first_name, auth.contact.last_name].filter(Boolean).join(" ");
    }
  });

  const submit$ = $(async () => {
    if (!form.name.trim() || !form.nationalNumber.trim()) {
      await toastError(tStatic(locale, "community.applyFailed"));
      return;
    }
    if (turnstileEnabled && !turnstileToken.value) {
      await toastError(tStatic(locale, "turnstile.required"));
      return;
    }

    const phoneCheck = validatePhone(
      form.dialCode,
      form.nationalNumber,
      props.phoneCountries,
      locale,
    );
    if (!phoneCheck.valid) {
      await toastError(phoneCheck.message || tStatic(locale, "community.applyFailed"));
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        await submitCommunityApplication(
          props.slug,
          {
            name: form.name.trim(),
            mobile: phoneCheck.fullPhone || form.nationalNumber.trim(),
            dial_code: form.dialCode,
            source: "web",
            ...(turnstileEnabled ? { turnstile_token: turnstileToken.value } : {}),
          },
          locale,
        );
        await toastSuccess(tStatic(locale, "community.applySuccess"));
        form.name = "";
        form.nationalNumber = "";
        turnstileToken.value = "";
        turnstileResetKey.value += 1;
      } catch (e) {
        turnstileResetKey.value += 1;
        const message =
          e instanceof ApiError
            ? e.message || tStatic(locale, "community.applyFailed")
            : tStatic(locale, "community.applyFailed");
        await toastError(message);
      }
    });
  });

  return (
    <section class="community-register" aria-labelledby="community-register-heading">
      <h2 id="community-register-heading">{tStatic(locale, "community.registerNow")}</h2>
      {props.registrationDetails ? (
        <p class="footer-muted">{props.registrationDetails}</p>
      ) : null}

      <div class="community-register__form">
        <label>
          <span>{tStatic(locale, "community.name")}</span>
          <input
            type="text"
            name="name"
            autocomplete="name"
            value={form.name}
            onInput$={(_, el) => {
              form.name = el.value;
            }}
          />
        </label>
        <label>
          <span>{tStatic(locale, "community.mobile")}</span>
          <div class="community-register__phone">
            <DialCodeSelect
              id="community-reg-dial"
              countries={props.phoneCountries}
              value={form.dialCode}
              onChange$={(code) => {
                form.dialCode = code;
              }}
            />
            <input
              type="tel"
              name="mobile"
              autocomplete="tel-national"
              inputMode="numeric"
              value={form.nationalNumber}
              onInput$={(_, el) => {
                form.nationalNumber = el.value;
              }}
            />
          </div>
        </label>

        {turnstileEnabled && turnstile.site_key ? (
          <TurnstileWidget
            siteKey={turnstile.site_key}
            token={turnstileToken}
            resetKey={turnstileResetKey.value}
          />
        ) : null}

        <button
          type="button"
          class="btn btn-primary"
          disabled={submitting.value}
          onClick$={submit$}
        >
          {submitting.value
            ? tStatic(locale, "community.submitting")
            : tStatic(locale, "community.submitApply")}
        </button>
      </div>
    </section>
  );
});
