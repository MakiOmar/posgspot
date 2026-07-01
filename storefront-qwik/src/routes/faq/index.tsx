import { $, component$, useSignal } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { FaqAccordion } from "~/components/content/content-blocks";
import { JsonLd } from "~/components/seo/json-ld";
import { FAQ_ENTRIES } from "~/lib/faq-content";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/layout";

export const useFaqPage = routeLoader$(async () => FAQ_ENTRIES);

export default component$(() => {
  const faq = useFaqPage();
  const openIndex = useSignal<number | null>(0);

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
      <nav class="content-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span aria-hidden="true">›</span>
        <span>FAQs</span>
      </nav>

      <h1 class="content-title">FAQs</h1>

      <FaqAccordion items={faq.value} openIndex={openIndex.value} onToggle$={toggle$} />

      <JsonLd data={faqSchema} />
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const title = `FAQs — ${settings.business_name}`;
  const description = `Frequently asked questions about shopping, repairs, warranty, and support at ${settings.business_name}.`;

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
    },
    settings,
  );
};
