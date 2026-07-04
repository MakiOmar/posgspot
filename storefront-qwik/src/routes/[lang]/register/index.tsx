import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, routeLoader$, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import { ApiError, fetchPhoneCountries, registerCustomer } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError } from "~/lib/notify";
import { validatePhone } from "~/lib/phone-validation";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam } from "~/routes/[lang]/layout";

export const useRegisterPhoneCountries = routeLoader$(async () => {
  try {
    const { data } = await fetchPhoneCountries();
    return data;
  } catch {
    return [];
  }
});

export default component$(() => {
  const auth = useAuth();
  const nav = useNavigate();
  const pending = usePendingState();
  const phoneCountries = useRegisterPhoneCountries();
  const form = useStore({
    first_name: "",
    last_name: "",
    email: "",
    dialCode: "+20",
    nationalNumber: "",
    mobile: "",
    password: "",
    password_confirmation: "",
  });
  const submitting = useSignal(false);
  const succeeded = useSignal(false);

  const { locale } = useI18n();
  const accountPath = localePath(locale, "/account");

  // Already signed in: go to account.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (auth.ready && auth.token) {
      nav(accountPath);
    }
  });

  const submit$ = $(async () => {
    if (form.password !== form.password_confirmation) {
      await toastError(tStatic(locale, "auth.passwordsMismatch"));
      return;
    }

    const phoneCheck = validatePhone(form.dialCode, form.nationalNumber, phoneCountries.value, locale);
    if (!phoneCheck.valid) {
      await toastError(phoneCheck.message);
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await registerCustomer({
          first_name: form.first_name,
          last_name: form.last_name,
          email: form.email,
          mobile: phoneCheck.fullPhone,
          password: form.password,
          password_confirmation: form.password_confirmation,
          dial_code: form.dialCode,
        });
        succeeded.value = true;
        auth.token = data.token;
        auth.contact = data.contact;
        await nav(accountPath);
      } catch (e) {
        if (e instanceof ApiError && e.errors) {
          const first = Object.values(e.errors)[0]?.[0];
          await toastError(first || tStatic(locale, "auth.registerFailed"));
        } else {
          await toastError(tStatic(locale, "auth.registerFailedRetry"));
        }
      }
    });
  });

  if (succeeded.value) {
    return (
      <section class="auth-page container">
        <div class="auth-card">
          <h1 class="page-title">{tStatic(locale, "auth.accountCreated")}</h1>
          <p class="alert alert-success">{tStatic(locale, "auth.welcomeAboard")}</p>
          <div class="auth-links">
            <Link href={accountPath} class="link-accent">
              {tStatic(locale, "auth.goToAccount")}
            </Link>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">{tStatic(locale, "auth.register")}</h1>

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-grid">
            <div class="form-field">
              <label for="first_name">{tStatic(locale, "forms.firstName")}</label>
              <input id="first_name" type="text" value={form.first_name} onInput$={(_, el) => (form.first_name = el.value)} required />
            </div>
            <div class="form-field">
              <label for="last_name">{tStatic(locale, "forms.lastName")}</label>
              <input id="last_name" type="text" value={form.last_name} onInput$={(_, el) => (form.last_name = el.value)} />
            </div>
            <div class="form-field">
              <label for="email">{tStatic(locale, "forms.email")}</label>
              <input id="email" type="email" autoComplete="email" value={form.email} onInput$={(_, el) => (form.email = el.value)} required />
            </div>
            <div class="form-field">
              <label for="register-mobile">{tStatic(locale, "forms.mobile")}</label>
              <PhoneInputWithDialCode
                id="register-mobile"
                countries={phoneCountries.value}
                dialCode={form.dialCode}
                nationalNumber={form.nationalNumber}
                required
                onChange$={(value) => {
                  form.dialCode = value.dialCode;
                  form.nationalNumber = value.nationalNumber;
                  form.mobile = value.fullPhone;
                }}
              />
            </div>
            <div class="form-field">
              <label for="password">{tStatic(locale, "forms.password")}</label>
              <input id="password" type="password" autoComplete="new-password" value={form.password} onInput$={(_, el) => (form.password = el.value)} required />
            </div>
            <div class="form-field">
              <label for="password_confirmation">{tStatic(locale, "forms.confirmPassword")}</label>
              <input id="password_confirmation" type="password" autoComplete="new-password" value={form.password_confirmation} onInput$={(_, el) => (form.password_confirmation = el.value)} required />
            </div>
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? tStatic(locale, "auth.creatingAccount") : tStatic(locale, "auth.register")}
          </button>
        </form>

        <div class="auth-links">
          <span>
            {tStatic(locale, "auth.alreadyHaveAccount")}{" "}
            <Link href={localePath(locale, "/login")} class="link-accent">
              {tStatic(locale, "auth.login")}
            </Link>
          </span>
        </div>
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "auth.register"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
