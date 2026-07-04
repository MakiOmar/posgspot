import { $, component$, useSignal, useStore } from "@builder.io/qwik";
import { Link, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, resetPassword } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const loc = useLocation();
  const nav = useNavigate();
  const pending = usePendingState();
  const { locale } = useI18n();
  const loginPath = localePath(locale, "/login");
  const email = loc.url.searchParams.get("email") || "";
  const token = loc.url.searchParams.get("token") || "";
  const form = useStore({ password: "", password_confirmation: "" });
  const submitting = useSignal(false);
  const succeeded = useSignal(false);

  const missingParams = !email || !token;

  const submit$ = $(async () => {
    if (form.password !== form.password_confirmation) {
      await toastError(tStatic(locale, "auth.passwordsMismatch"));
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        await resetPassword({
          email,
          token,
          password: form.password,
          password_confirmation: form.password_confirmation,
        });
        succeeded.value = true;
        await nav(loginPath);
      } catch (e) {
        await toastError(
          e instanceof ApiError && e.status === 422
            ? e.message || tStatic(locale, "auth.invalidOrExpiredLink")
            : tStatic(locale, "auth.resetFailed"),
        );
      }
    });
  });

  if (missingParams) {
    return (
      <section class="auth-page container">
        <div class="auth-card">
          <h1 class="page-title">{tStatic(locale, "auth.invalidResetLink")}</h1>
          <p class="alert alert-error">{tStatic(locale, "auth.invalidResetLinkBody")}</p>
          <div class="auth-links">
            <Link href={localePath(locale, "/forgot-password")} class="link-accent">
              {tStatic(locale, "auth.requestNewResetLink")}
            </Link>
            <Link href={loginPath} class="link-accent">
              {tStatic(locale, "auth.backToSignIn")}
            </Link>
          </div>
        </div>
      </section>
    );
  }

  if (succeeded.value) {
    return (
      <section class="auth-page container">
        <div class="auth-card">
          <h1 class="page-title">{tStatic(locale, "auth.passwordUpdated")}</h1>
          <p class="alert alert-success">{tStatic(locale, "auth.passwordUpdatedBody")}</p>
          <div class="auth-links">
            <Link href={loginPath} class="link-accent">
              {tStatic(locale, "auth.signInNow")}
            </Link>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">{tStatic(locale, "auth.chooseNewPassword")}</h1>
        <p class="footer-muted" style={{ marginBottom: "1rem" }}>
          {tStatic(locale, "auth.enterNewPasswordFor")} <strong>{email}</strong>.
        </p>

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-field form-field--full">
            <label for="password">{tStatic(locale, "forms.newPassword")}</label>
            <input
              id="password"
              type="password"
              autoComplete="new-password"
              value={form.password}
              onInput$={(_, el) => (form.password = el.value)}
              required
              minLength={8}
            />
          </div>
          <div class="form-field form-field--full">
            <label for="password_confirmation">{tStatic(locale, "forms.confirmNewPassword")}</label>
            <input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              value={form.password_confirmation}
              onInput$={(_, el) => (form.password_confirmation = el.value)}
              required
              minLength={8}
            />
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? tStatic(locale, "auth.updating") : tStatic(locale, "auth.updatePassword")}
          </button>
        </form>

        <div class="auth-links">
          <Link href={loginPath} class="link-accent">
            {tStatic(locale, "auth.backToSignIn")}
          </Link>
        </div>
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "auth.resetPassword"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
