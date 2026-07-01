import { component$, Slot, $, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import { logoutCustomer } from "~/lib/api";
import { accountDisplayName, isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { confirmSignOut } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";

export default component$(() => {
  const auth = useAuth();
  const loc = useLocation();
  const nav = useNavigate();
  const pending = usePendingState();
  const loggingOut = useSignal(false);

  // Client-side guard: tokens live in localStorage, resolved after hydration.
  // Redirect to login only once we know the session state (`ready`).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (auth.ready && !auth.token) {
      const next = encodeURIComponent(loc.url.pathname);
      nav(`/login?next=${next}`);
    }
  });

  const logout$ = $(async () => {
    const confirmed = await confirmSignOut();
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
      await nav("/login");
    });
  });

  const navItems = [
    { href: "/account", label: "Overview" },
    { href: "/account/orders", label: "Orders" },
    { href: "/account/profile", label: "Profile & address" },
  ];

  // Avoid flashing protected content before hydration / when signed out.
  if (!auth.ready || !isAuthenticated(auth)) {
    return (
      <section class="container" style={{ padding: "3rem 0" }}>
        <p class="footer-muted">Loading your account…</p>
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
              item.href === "/account"
                ? loc.url.pathname === "/account" || loc.url.pathname === "/account/"
                : loc.url.pathname.startsWith(item.href);
            return (
              <Link key={item.href} href={item.href} class={active ? "active" : ""}>
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
            {loggingOut.value ? "Signing out…" : "Sign out"}
          </button>
        </nav>
      </aside>
      <div class="account-content">
        <Slot />
      </div>
    </section>
  );
});
