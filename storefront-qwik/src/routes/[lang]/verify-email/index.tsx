import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, resendEmailVerification, verifyEmail } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const loc = useLocation();
  const nav = useNavigate();
  const auth = useAuth();
  const pending = usePendingState();
  const { locale } = useI18n();
  const email = loc.url.searchParams.get("email") || auth.contact?.email || "";
  const nextParam = loc.url.searchParams.get("next") || "/account";
  const code = useSignal("");
  const submitting = useSignal(false);
  const sending = useSignal(false);
  const sentOnce = useSignal(false);

  const sendCode$ = $(async (showToast: boolean) => {
    if (!email && !auth.token) {
      return;
    }
    await withPendingFeedback(pending, sending, async () => {
      try {
        await resendEmailVerification(
          { email: email || undefined },
          auth.token,
        );
        if (showToast) {
          await toastSuccess(tStatic(locale, "auth.codeSent"));
        }
      } catch (e) {
        await toastError(
          e instanceof ApiError
            ? e.message
            : tStatic(locale, "auth.codeSendFailed"),
        );
      }
    });
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    if (sentOnce.value) {
      return;
    }
    sentOnce.value = true;
    await sendCode$(true);
  });

  const submit$ = $(async () => {
    const trimmed = code.value.replace(/\D/g, "").slice(0, 6);
    if (trimmed.length !== 6) {
      await toastError(tStatic(locale, "auth.verifyHint"));
      return;
    }
    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await verifyEmail(
          { code: trimmed, email: email || undefined },
          auth.token,
        );
        if (data.contact) {
          auth.contact = data.contact;
        }
        await toastSuccess(tStatic(locale, "auth.verified"));
        const dest = nextParam.startsWith("/") ? nextParam : `/${nextParam}`;
        await nav(localePath(locale, dest));
      } catch (e) {
        await toastError(
          e instanceof ApiError ? e.message : tStatic(locale, "auth.verifyFailed"),
        );
      }
    });
  });

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">{tStatic(locale, "auth.verifyTitle")}</h1>
        <p class="footer-muted" style={{ marginBottom: "1rem" }}>
          {sending.value
            ? tStatic(locale, "auth.codeSending")
            : tStatic(locale, "auth.verifyHint")}
        </p>

        <form preventdefault:submit onSubmit$={submit$} class="account-form">
          <div class="form-field form-field--full">
            <label for="verify-email">{tStatic(locale, "auth.email")}</label>
            <input id="verify-email" type="email" value={email} readOnly />
          </div>
          <div class="form-field form-field--full">
            <label for="verify-code">{tStatic(locale, "auth.verifyCode")}</label>
            <input
              id="verify-code"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={6}
              value={code.value}
              onInput$={(_, el) => {
                code.value = el.value.replace(/\D/g, "").slice(0, 6);
              }}
              required
            />
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value
              ? tStatic(locale, "common.loading")
              : tStatic(locale, "auth.verify")}
          </button>
        </form>

        <div class="auth-links">
          <button
            type="button"
            class="link-accent"
            disabled={sending.value}
            onClick$={() => sendCode$(true)}
          >
            {sending.value
              ? tStatic(locale, "auth.codeSending")
              : tStatic(locale, "auth.resendCode")}
          </button>
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
    title: tStatic(lang, "auth.verifyTitle"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
