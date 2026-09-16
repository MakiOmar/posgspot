import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, exchangeSocialCode } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { useLangParam } from "~/routes/[lang]/layout";

/**
 * Completes web Socialite flow: one-time `code` → Sanctum session (never in the URL long-term).
 */
export default component$(() => {
  const loc = useLocation();
  const nav = useNavigate();
  const auth = useAuth();
  const { locale } = useI18n();
  const status = useSignal<"working" | "error" | "done">("working");
  const message = useSignal("");
  const ran = useSignal(false);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    if (ran.value) {
      return;
    }
    ran.value = true;

    const error = loc.url.searchParams.get("error");
    if (error) {
      status.value = "error";
      message.value = error;
      await toastError(error);
      return;
    }

    const code = loc.url.searchParams.get("code");
    if (!code) {
      status.value = "error";
      message.value = tStatic(locale, "auth.socialFailed");
      return;
    }

    try {
      const { data } = await exchangeSocialCode(code);
      auth.token = data.token;
      auth.contact = data.contact;
      status.value = "done";
      await toastSuccess(tStatic(locale, "auth.socialSuccess"));
      const next = loc.url.searchParams.get("next") || "/account";
      const dest = next.startsWith("/") ? next : `/${next}`;
      await nav(localePath(locale, dest));
    } catch (e) {
      status.value = "error";
      message.value =
        e instanceof ApiError ? e.message : tStatic(locale, "auth.socialFailed");
      await toastError(message.value);
    }
  });

  return (
    <section class="auth-page container">
      <div class="auth-card">
        <h1 class="page-title">{tStatic(locale, "auth.socialTitle")}</h1>
        {status.value === "working" ? (
          <p class="footer-muted">{tStatic(locale, "common.loading")}</p>
        ) : null}
        {status.value === "error" ? (
          <>
            <p class="alert alert-error">{message.value}</p>
            <div class="auth-links">
              <Link href={localePath(locale, "/login")} class="link-accent">
                {tStatic(locale, "auth.backToSignIn")}
              </Link>
            </div>
          </>
        ) : null}
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "auth.socialTitle"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
