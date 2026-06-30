import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { useAuth } from "~/lib/auth-context";

export default component$(() => {
  const auth = useAuth();
  const c = auth.contact;

  return (
    <div>
      <h1 class="page-title">My account</h1>
      <p class="footer-muted" style={{ marginBottom: "1.5rem" }}>
        Manage your orders, profile and delivery address.
      </p>

      <div class="account-cards">
        <Link href="/account/orders" class="account-card">
          <strong>Orders</strong>
          <span class="footer-muted">View your order history and status</span>
        </Link>
        <Link href="/account/profile" class="account-card">
          <strong>Profile &amp; address</strong>
          <span class="footer-muted">Update your details and delivery address</span>
        </Link>
      </div>

      {c ? (
        <div class="account-summary">
          <h2>Your details</h2>
          <dl class="account-detail-list">
            <div>
              <dt>Name</dt>
              <dd>{c.name || "—"}</dd>
            </div>
            <div>
              <dt>Email</dt>
              <dd>{c.email || "—"}</dd>
            </div>
            <div>
              <dt>Mobile</dt>
              <dd>{c.mobile || "—"}</dd>
            </div>
          </dl>
        </div>
      ) : null}
    </div>
  );
});

export const head: DocumentHead = {
  title: "My account",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
