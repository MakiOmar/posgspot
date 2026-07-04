import { component$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { MaintenancePage } from "~/components/layout/maintenance-page";
import { tStatic } from "~/lib/i18n/context";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  useLangParam();
  const settings = useSiteSettings();

  return <MaintenancePage settings={settings.value} />;
});

export const head: DocumentHead = ({ resolveValue, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = params.lang === "ar" ? "ar" : "en";
  const name = settings.business_name;

  return withStorefrontThemeHead(
    {
      title: tStatic(lang, "maintenance.seoTitle", { businessName: name }),
      meta: [
        {
          name: "description",
          content: tStatic(lang, "maintenance.seoDescription", { businessName: name }),
        },
        { name: "robots", content: "noindex, nofollow" },
      ],
    },
    settings,
  );
};
