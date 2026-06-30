import { $, component$, useSignal } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { forgotPassword } from "~/lib/api";

export default component$(() => {
  const email = useSignal("");
  const submitting = useSignal(false);
  const message = useSignal<string | null>(null);
  const error = useSignal<string | null>(null);

  const submit$ = $(async () => {
    submitting.value = true;
    message.value = null;
    error.value = null;
    try {
      const { data } = await forgotPassword(email.value);
      // Backend always returns a generic message (no account enumeration).
      message.value = data.message || "If the email exists, a reset link has been sent.";
    } catch {
      error.value = "Could not process the request. Please try again.";
    } finally {
      submitting.value = false;
    }
  });

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">Reset password</h1>
        <p class="footer-muted" style={{ marginBottom: "1rem" }}>
          Enter your email and we’ll send you a link to reset your password.
        </p>

        {message.value ? <p class="alert alert-success">{message.value}</p> : null}
        {error.value ? <p class="alert alert-error">{error.value}</p> : null}

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-field form-field--full">
            <label for="email">Email</label>
            <input
              id="email"
              type="email"
              autoComplete="email"
              value={email.value}
              onInput$={(_, el) => (email.value = el.value)}
              required
            />
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? "Sending…" : "Send reset link"}
          </button>
        </form>

        <div class="auth-links">
          <Link href="/login" class="link-accent">
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
