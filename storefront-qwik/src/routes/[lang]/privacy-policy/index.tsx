import { component$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { LegalDocumentView } from "~/components/content/legal-document";
import { getPrivacyPolicy } from "~/lib/legal-content";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const lang = useLangParam();
  return <LegalDocumentView doc={getPrivacyPolicy(lang.value)} />;
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const doc = getPrivacyPolicy(lang);
  return withStorefrontThemeHead(
    {
      title: doc.title,
      meta: [
        {
          name: "description",
          content: doc.intro || doc.title,
        },
      ],
    },
    settings,
  );
};
