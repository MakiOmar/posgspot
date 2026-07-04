import { $, component$, useSignal } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { FaqAccordion } from "~/components/content/content-blocks";
import { JsonLd } from "~/components/seo/json-ld";
import { getFaqEntries } from "~/lib/faq-content";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useFaqPage = routeLoader$(({ params }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  return getFaqEntries(locale);
});

export default component$(() => {
  const faq = useFaqPage();
  const openIndex = useSignal<number | null>(0);
  const { locale } = useI18n();

  const toggle$ = $((index: number) => {
    openIndex.value = openIndex.value === index ? null : index;
  });

  const faqSchema = {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    mainEntity: faq.value.map((item) => ({
      "@type": "Question",
      name: item.question,
      acceptedAnswer: {
        "@type": "Answer",
        text: item.answer,
      },
    })),
  };

  return (
    <article class="content-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "nav.faq")}</span>
      </nav>

      <h1 class="content-title">{tStatic(locale, "nav.faq")}</h1>

      <FaqAccordion items={faq.value} openIndex={openIndex.value} onToggle$={toggle$} />

      <JsonLd data={faqSchema} />
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = `${tStatic(lang, "nav.faq")} — ${settings.business_name}`;
  const description = `${tStatic(lang, "nav.faq")} — ${settings.business_name}`;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { name: "twitter:card", content: "summary" },
      ],
      links: publicSeoLinks(url.origin, "/faq", lang),
    },
    settings,
  );
};
