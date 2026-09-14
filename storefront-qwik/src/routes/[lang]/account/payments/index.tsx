import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const { locale } = useI18n();

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.paymentsPayouts")}</h1>
      <div class="account-cards">
        <Link href={localePath(locale, "/account/payments/methods")} class="account-card">
          <strong>{tStatic(locale, "account.paymentMethods")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.paymentMethodsEmpty")}</span>
        </Link>
        <Link href={localePath(locale, "/account/payments/list")} class="account-card">
          <strong>{tStatic(locale, "account.paymentsTab")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.ordersCardDesc")}</span>
        </Link>
        <Link href={localePath(locale, "/account/payments/credits")} class="account-card">
          <strong>{tStatic(locale, "account.creditsCoupons")}</strong>
          <span class="footer-muted">{tStatic(locale, "account.paymentsCardDesc")}</span>
        </Link>
      </div>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.paymentsPayouts"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
