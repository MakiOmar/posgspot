import { component$, Slot, $, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import { logoutCustomer } from "~/lib/api";
import { accountDisplayName, isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath, stripLocalePrefix } from "~/lib/i18n/paths";
import { confirmAction } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";

export default component$(() => {
  const auth = useAuth();
  const loc = useLocation();
  const nav = useNavigate();
  const pending = usePendingState();
  const loggingOut = useSignal(false);
  const { locale, dir } = useI18n();
  const barePath = stripLocalePrefix(loc.url.pathname);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (auth.ready && !auth.token) {
      const next = encodeURIComponent(loc.url.pathname);
      nav(localePath(locale, `/login?next=${next}`));
    }
  });

  const logoutLabel = tStatic(locale, "auth.logout");
  const logoutConfirmText = tStatic(locale, "auth.logoutConfirm");
  const cancelLabel = tStatic(locale, "common.cancel");

  const logout$ = $(async () => {
    const confirmed = await confirmAction({
      title: `${logoutLabel}?`,
      text: logoutConfirmText,
      confirmText: logoutLabel,
      cancelText: cancelLabel,
      icon: "warning",
      dir,
    });
    if (!confirmed) {
      return;
    }

    await withPendingFeedback(pending, loggingOut, async () => {
      const token = auth.token;
      auth.token = null;
      auth.contact = null;
      if (token) {
        try {
          await logoutCustomer(token);
        } catch {
          // Best effort: token may already be invalid server-side.
        }
      }
      await nav(localePath(locale, "/login"));
    });
  });

  const navItems = [
    { path: "/account", label: tStatic(locale, "account.dashboard") },
    { path: "/account/orders", label: tStatic(locale, "account.orders") },
    { path: "/account/profile", label: tStatic(locale, "account.profile") },
  ];

  if (!auth.ready || !isAuthenticated(auth)) {
    return (
      <section class="container" style={{ padding: "3rem 0" }}>
        <p class="footer-muted">{tStatic(locale, "common.loading")}</p>
      </section>
    );
  }

  return (
    <section class="account-shell container">
      <aside class="account-sidebar">
        <p class="account-greeting">Hi, {accountDisplayName(auth)}</p>
        <nav class="account-nav" aria-label="Account">
          {navItems.map((item) => {
            const active =
              item.path === "/account"
                ? barePath === "/account" || barePath === "/account/"
                : barePath.startsWith(item.path);
            return (
              <Link
                key={item.path}
                href={localePath(locale, item.path)}
                class={active ? "active" : ""}
              >
                {item.label}
              </Link>
            );
          })}
          <button
            type="button"
            class="account-logout"
            onClick$={logout$}
            disabled={loggingOut.value}
          >
            {loggingOut.value ? tStatic(locale, "common.loading") : tStatic(locale, "auth.logout")}
          </button>
        </nav>
      </aside>
      <div class="account-content">
        <Slot />
      </div>
    </section>
  );
});
