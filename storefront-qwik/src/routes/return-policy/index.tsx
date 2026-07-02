import { component$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { LegalDocumentView } from "~/components/content/legal-document";
import { RETURN_POLICY } from "~/lib/legal-content";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/layout";

export default component$(() => {
  return <LegalDocumentView doc={RETURN_POLICY} />;
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  return withStorefrontThemeHead(
    {
      title: "Return & Exchange Policy",
      meta: [
        {
          name: "description",
          content: `Return and exchange policy for ${settings.business_name} purchases.`,
        },
      ],
    },
    settings,
  );
};
