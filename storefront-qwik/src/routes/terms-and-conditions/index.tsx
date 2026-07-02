import { component$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { LegalDocumentView } from "~/components/content/legal-document";
import { TERMS_AND_CONDITIONS } from "~/lib/legal-content";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/layout";

export default component$(() => {
  return <LegalDocumentView doc={TERMS_AND_CONDITIONS} />;
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  return withStorefrontThemeHead(
    {
      title: "Terms & Conditions",
      meta: [
        {
          name: "description",
          content: `Terms and conditions for shopping at ${settings.business_name}.`,
        },
      ],
    },
    settings,
  );
};
