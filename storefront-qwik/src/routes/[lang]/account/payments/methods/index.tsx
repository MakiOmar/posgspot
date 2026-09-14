import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastSuccess } from "~/lib/notify";
import { useLangParam } from "~/routes/[lang]/layout";

export default component$(() => {
  const { locale } = useI18n();

  return (
    <div>
      <p class="footer-muted" style={{ marginBottom: "1rem" }}>
        <Link href={localePath(locale, "/account/payments")} class="link-accent">
          {tStatic(locale, "account.paymentsPayouts")}
        </Link>
      </p>
      <h1 class="page-title">{tStatic(locale, "account.paymentMethods")}</h1>
      <p class="empty-state">{tStatic(locale, "account.paymentMethodsEmpty")}</p>
      <button
        type="button"
        class="btn btn-primary"
        onClick$={() => toastSuccess(tStatic(locale, "account.paymentMethodsSoon"))}
      >
        {tStatic(locale, "account.addPaymentMethod")}
      </button>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.paymentMethods"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
