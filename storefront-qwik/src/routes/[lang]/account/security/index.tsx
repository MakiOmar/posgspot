import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { SocialLoginButtons } from "~/components/auth/social-login-buttons";
import {
  ApiError,
  changePassword,
  disconnectSocialProvider,
  fetchSocialIdentities,
  requestAccountDeletion,
  type SocialProvider,
} from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { confirmAction, toastError, toastInfo, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

type IdentityRow = { provider: string; email: string | null; connected: boolean };

export default component$(() => {
  const auth = useAuth();
  const { locale } = useI18n();
  const settings = useSiteSettings();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const loadingSocial = useSignal(true);
  const identities = useSignal<IdentityRow[]>([]);
  const form = useStore({
    current_password: "",
    password: "",
    password_confirmation: "",
  });

  const googleEnabled = !!settings.value.social_login?.google_enabled;
  const facebookEnabled = !!settings.value.social_login?.facebook_enabled;
  const socialEnabled = googleEnabled || facebookEnabled;

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    if (!auth.token || !socialEnabled) {
      loadingSocial.value = false;
      return;
    }
    loadingSocial.value = true;
    try {
      const { data } = await fetchSocialIdentities(auth.token);
      identities.value = data.identities || [];
    } catch {
      identities.value = [];
    } finally {
      loadingSocial.value = false;
    }
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

  const disconnect$ = $(async (provider: SocialProvider) => {
    if (!auth.token) {
      return;
    }
    try {
      const { data } = await disconnectSocialProvider(auth.token, provider);
      identities.value = data.identities || [];
      await toastSuccess(tStatic(locale, "auth.socialDisconnected"));
    } catch (e) {
      await toastError(
        e instanceof ApiError
          ? e.message || tStatic(locale, "auth.socialDisconnectFailed")
          : tStatic(locale, "auth.socialDisconnectFailed"),
      );
    }
  });

  const requestDelete$ = $(async () => {
    if (!auth.token) {
      return;
    }
    if (auth.contact?.delete_requested) {
      await toastInfo(tStatic(locale, "account.deleteRequested"));
      return;
    }

    const ok = await confirmAction({
      title: tStatic(locale, "account.deleteRequest"),
      text: tStatic(locale, "account.deleteConfirm"),
      confirmText: tStatic(locale, "account.deleteRequest"),
      cancelText: tStatic(locale, "common.cancel"),
      icon: "warning",
      danger: true,
      dir: locale === "ar" ? "rtl" : "ltr",
    });
    if (!ok) {
      return;
    }

    try {
      const { data } = await requestAccountDeletion(auth.token);
      if (data.contact) {
        auth.contact = data.contact;
      } else if (auth.contact) {
        auth.contact = { ...auth.contact, delete_requested: true };
      }
      await toastSuccess(tStatic(locale, "account.deleteRequested"));
    } catch (e) {
      await toastError(
        e instanceof ApiError
          ? e.message || tStatic(locale, "account.deleteFailed")
          : tStatic(locale, "account.deleteFailed"),
      );
    }
  });

  const providerLabel = (provider: string) => {
    if (provider === "google") return "Google";
    if (provider === "facebook") return "Facebook";
    return provider;
  };

  const connectedProviders = new Set(
    identities.value.filter((i) => i.connected).map((i) => i.provider),
  );

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.loginSecurity")}</h1>
      <p class="footer-muted" style={{ marginBottom: "1.5rem" }}>
        {tStatic(locale, "account.loginSecurityHint")}
      </p>

      <form class="account-form" preventdefault:submit onSubmit$={submit$}>
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

      {socialEnabled ? (
        <section class="account-form social-accounts" style={{ marginTop: "2rem" }}>
          <h2>{tStatic(locale, "account.socialAccounts")}</h2>
          <p class="footer-muted">{tStatic(locale, "account.socialAccountsHint")}</p>

          {loadingSocial.value ? (
            <p class="footer-muted">{tStatic(locale, "common.loading")}</p>
          ) : (
            <ul class="social-accounts__list">
              {googleEnabled ? (
                <li class="social-accounts__row">
                  <div>
                    <strong>{providerLabel("google")}</strong>
                    {connectedProviders.has("google") ? (
                      <span class="footer-muted"> · {tStatic(locale, "auth.connected")}</span>
                    ) : null}
                  </div>
                  {connectedProviders.has("google") ? (
                    <button
                      type="button"
                      class="btn btn-secondary"
                      onClick$={() => disconnect$("google")}
                    >
                      {tStatic(locale, "auth.disconnect")}
                    </button>
                  ) : null}
                </li>
              ) : null}
              {facebookEnabled ? (
                <li class="social-accounts__row">
                  <div>
                    <strong>{providerLabel("facebook")}</strong>
                    {connectedProviders.has("facebook") ? (
                      <span class="footer-muted"> · {tStatic(locale, "auth.connected")}</span>
                    ) : null}
                  </div>
                  {connectedProviders.has("facebook") ? (
                    <button
                      type="button"
                      class="btn btn-secondary"
                      onClick$={() => disconnect$("facebook")}
                    >
                      {tStatic(locale, "auth.disconnect")}
                    </button>
                  ) : null}
                </li>
              ) : null}
            </ul>
          )}

          {!connectedProviders.has("google") || !connectedProviders.has("facebook") ? (
            <SocialLoginButtons intent="link" next="/account/security" />
          ) : null}
        </section>
      ) : null}

      <section class="account-form account-danger-zone" style={{ marginTop: "2.5rem" }}>
        <h2>{tStatic(locale, "account.deleteSection")}</h2>
        <p class="footer-muted">{tStatic(locale, "account.deleteHint")}</p>
        {auth.contact?.delete_requested ? (
          <p class="alert alert-info" role="status">
            {tStatic(locale, "account.deleteRequested")}
          </p>
        ) : (
          <button type="button" class="btn btn-danger" onClick$={requestDelete$}>
            {tStatic(locale, "account.deleteMyAccount")}
          </button>
        )}
      </section>
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
