import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, loginCustomer } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const auth = useAuth();
  const nav = useNavigate();
  const loc = useLocation();
  const pending = usePendingState();
  const form = useStore({ login: "", password: "" });
  const submitting = useSignal(false);

  const { locale } = useI18n();

  const defaultNext = localePath(locale, "/account");
  const nextUrl = loc.url.searchParams.get("next") || defaultNext;

  // If already signed in, skip the login page.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (auth.ready && auth.token) {
      nav(nextUrl);
    }
  });

  const submit$ = $(async () => {
    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await loginCustomer({ login: form.login, password: form.password });
        auth.token = data.token;
        auth.contact = data.contact;
        await nav(nextUrl);
      } catch (e) {
        await toastError(
          e instanceof ApiError && e.status === 422
            ? tStatic(locale, "auth.invalidCredentials")
            : tStatic(locale, "auth.loginFailed"),
        );
      }
    });
  });

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">{tStatic(locale, "auth.login")}</h1>

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-field form-field--full">
            <label for="login">{tStatic(locale, "forms.emailOrMobile")}</label>
            <input
              id="login"
              type="text"
              autoComplete="username"
              value={form.login}
              onInput$={(_, el) => (form.login = el.value)}
              required
            />
          </div>
          <div class="form-field form-field--full">
            <label for="password">{tStatic(locale, "forms.password")}</label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              value={form.password}
              onInput$={(_, el) => (form.password = el.value)}
              required
            />
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? tStatic(locale, "auth.signingIn") : tStatic(locale, "auth.login")}
          </button>
        </form>

        <div class="auth-links">
          <Link href={localePath(locale, "/forgot-password")} class="link-accent">
            {tStatic(locale, "auth.forgotPassword")}
          </Link>
          <span>
            {tStatic(locale, "auth.newHere")}{" "}
            <Link href={localePath(locale, "/register")} class="link-accent">
              {tStatic(locale, "auth.createAnAccount")}
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
    title: tStatic(lang, "auth.login"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
