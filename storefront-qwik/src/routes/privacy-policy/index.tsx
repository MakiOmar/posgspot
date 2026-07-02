import { component$ } from "@builder.io/qwik";
import { type DocumentHead } from "@builder.io/qwik-city";
import { LegalDocumentView } from "~/components/content/legal-document";
import { PRIVACY_POLICY } from "~/lib/legal-content";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/layout";

export default component$(() => {
  return <LegalDocumentView doc={PRIVACY_POLICY} />;
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  return withStorefrontThemeHead(
    {
      title: "Privacy Policy",
      meta: [
        {
          name: "description",
          content: `How ${settings.business_name} collects and uses your personal information.`,
        },
      ],
    },
    settings,
  );
};
