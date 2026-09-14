import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { CustomerQrCode } from "~/components/account/customer-qr-code";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { needsEmailVerification } from "~/lib/verification";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const auth = useAuth();
  const { locale } = useI18n();
  const c = auth.contact;

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.dashboard")}</h1>
      <p class="footer-muted" style={{ marginBottom: "1.5rem" }}>
        {tStatic(locale, "account.intro")}
      </p>

      {needsEmailVerification(c) ? (
        <p class="alert alert-error" style={{ marginBottom: "1.5rem" }}>
          {tStatic(locale, "account.verifyEmailHint")}{" "}
          <Link
            href={localePath(
              locale,
              `/verify-email?email=${encodeURIComponent(c?.email || "")}&next=/account`,
            )}
            class="link-accent"
          >
            {tStatic(locale, "account.verifyEmail")}
          </Link>
        </p>
      ) : null}

      <div class="account-cards">
        <Link href={localePath(locale, "/account/profile")} class="account-card">
          <strong>{tStatic(locale, "account.profileAddress")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.profileCardDesc")}</span>
        </Link>
        <Link href={localePath(locale, "/account/security")} class="account-card">
          <strong>{tStatic(locale, "account.loginSecurity")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.loginSecurityHint")}</span>
        </Link>
        <Link href={localePath(locale, "/account/payments")} class="account-card">
          <strong>{tStatic(locale, "account.paymentsPayouts")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.paymentsCardDesc")}</span>
        </Link>
      </div>

      {c ? (
        <div class="account-overview-grid">
          <div class="account-summary">
            <h2>{tStatic(locale, "account.yourDetails")}</h2>
            <dl class="account-detail-list">
              <div>
                <dt>{tStatic(locale, "forms.name")}</dt>
                <dd>{c.name || "—"}</dd>
              </div>
              <div>
                <dt>{tStatic(locale, "forms.email")}</dt>
                <dd>{c.email || "—"}</dd>
              </div>
              <div>
                <dt>{tStatic(locale, "forms.mobile")}</dt>
                <dd>{c.mobile || "—"}</dd>
              </div>
            </dl>
          </div>
          <CustomerQrCode name={c.name} email={c.email} mobile={c.mobile} />
        </div>
      ) : null}
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.dashboard"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
