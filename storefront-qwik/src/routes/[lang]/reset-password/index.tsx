import { $, component$, useSignal, useStore } from "@builder.io/qwik";
import { Link, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, resetPassword } from "~/lib/api";
import { toastError } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { withPendingFeedback } from "~/lib/with-pending";

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
      await toastError("Passwords do not match.");
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
            ? e.message || "Invalid or expired reset link."
            : "Could not reset your password. Please try again.",
        );
      }
    });
  });

  if (missingParams) {
    return (
      <section class="auth-page container">
        <div class="auth-card">
          <h1 class="page-title">Invalid reset link</h1>
          <p class="alert alert-error">
            This password reset link is incomplete or invalid. Request a new link below.
          </p>
          <div class="auth-links">
            <Link href={localePath(locale, "/forgot-password")} class="link-accent">
              Request a new reset link
            </Link>
            <Link href={loginPath} class="link-accent">
              Back to sign in
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
          <h1 class="page-title">Password updated</h1>
          <p class="alert alert-success">
            Your password has been changed — redirecting you to sign in…
          </p>
          <div class="auth-links">
            <Link href={loginPath} class="link-accent">
              Sign in now
            </Link>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">Choose a new password</h1>
        <p class="footer-muted" style={{ marginBottom: "1rem" }}>
          Enter a new password for <strong>{email}</strong>.
        </p>

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-field form-field--full">
            <label for="password">New password</label>
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
            <label for="password_confirmation">Confirm new password</label>
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
            {submitting.value ? "Updating…" : "Update password"}
          </button>
        </form>

        <div class="auth-links">
          <Link href={loginPath} class="link-accent">
            Back to sign in
          </Link>
        </div>
      </div>
    </section>
  );
});

export const head: DocumentHead = {
  title: "Reset password",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
