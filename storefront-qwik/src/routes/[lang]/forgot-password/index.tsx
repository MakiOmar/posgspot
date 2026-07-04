import { $, component$, useSignal } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { forgotPassword } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const { locale } = useI18n();
  const email = useSignal("");
  const submitting = useSignal(false);
  const pending = usePendingState();

  const submit$ = $(async () => {
    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await forgotPassword(email.value);
        await toastSuccess(data.message || tStatic(locale, "auth.resetLinkSent"));
      } catch {
        await toastError(tStatic(locale, "auth.resetRequestFailed"));
      }
    });
  });

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">{tStatic(locale, "auth.resetPassword")}</h1>
        <p class="footer-muted" style={{ marginBottom: "1rem" }}>
          {tStatic(locale, "auth.resetPasswordHint")}
        </p>

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-field form-field--full">
            <label for="email">{tStatic(locale, "forms.email")}</label>
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
            {submitting.value ? tStatic(locale, "auth.sending") : tStatic(locale, "auth.sendResetLink")}
          </button>
        </form>

        <div class="auth-links">
          <Link href={localePath(locale, "/login")} class="link-accent">
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
