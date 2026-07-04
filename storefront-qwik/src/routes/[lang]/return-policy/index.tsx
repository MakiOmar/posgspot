import { component$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { LegalDocumentView } from "~/components/content/legal-document";
import { getReturnPolicy } from "~/lib/legal-content";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const lang = useLangParam();
  return <LegalDocumentView doc={getReturnPolicy(lang.value)} />;
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const doc = getReturnPolicy(lang);
  return withStorefrontThemeHead(
    {
      title: doc.title,
      meta: [
        {
          name: "description",
          content: doc.intro || doc.title,
        },
      ],
      links: publicSeoLinks(url.origin, "/return-policy", lang),
    },
    settings,
  );
};
