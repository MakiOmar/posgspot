import { $, component$, useSignal, useStore } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, changePassword } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const auth = useAuth();
  const { locale } = useI18n();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const form = useStore({
    current_password: "",
    password: "",
    password_confirmation: "",
  });

  const submit$ = $(async () => {
    if (form.password !== form.password_confirmation) {
      await toastError(tStatic(locale, "auth.passwordsMismatch"));
      return;
    }
    if (!auth.token) {
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await changePassword(auth.token as string, {
          current_password: form.current_password,
          password: form.password,
          password_confirmation: form.password_confirmation,
        });
        auth.token = data.token;
        auth.contact = data.contact;
        form.current_password = "";
        form.password = "";
        form.password_confirmation = "";
        await toastSuccess(tStatic(locale, "account.passwordChanged"));
      } catch (e) {
        await toastError(
          e instanceof ApiError
            ? e.message || tStatic(locale, "account.saveFailed")
            : tStatic(locale, "account.saveFailed"),
        );
      }
    });
  });

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.loginSecurity")}</h1>
      <p class="footer-muted" style={{ marginBottom: "1.5rem" }}>
        {tStatic(locale, "account.loginSecurityHint")}
      </p>

      <form
        class="account-form"
        preventdefault:submit
        onSubmit$={submit$}
      >
        <h2>{tStatic(locale, "account.changePassword")}</h2>
        <label for="current_password">{tStatic(locale, "forms.currentPassword")}</label>
        <input
          id="current_password"
          type="password"
          autoComplete="current-password"
          value={form.current_password}
          onInput$={(_, el) => (form.current_password = el.value)}
          required
        />
        <label for="new_password">{tStatic(locale, "forms.newPassword")}</label>
        <input
          id="new_password"
          type="password"
          autoComplete="new-password"
          value={form.password}
          onInput$={(_, el) => (form.password = el.value)}
          required
        />
        <label for="password_confirmation">{tStatic(locale, "forms.confirmNewPassword")}</label>
        <input
          id="password_confirmation"
          type="password"
          autoComplete="new-password"
          value={form.password_confirmation}
          onInput$={(_, el) => (form.password_confirmation = el.value)}
          required
        />
        <button type="submit" class="btn btn-primary" disabled={submitting.value}>
          {submitting.value
            ? tStatic(locale, "account.saving")
            : tStatic(locale, "auth.updatePassword")}
        </button>
      </form>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.loginSecurity"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
