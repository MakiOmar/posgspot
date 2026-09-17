import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { LegalDocumentView } from "~/components/content/legal-document";
import { JsonLd } from "~/components/seo/json-ld";
import {
  deleteAccountHowToJsonLd,
  getDeleteAccountGuide,
} from "~/lib/delete-account-content";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

/** Public instructions for requesting account deletion (website + app). */
export default component$(() => {
  const lang = useLangParam();
  const { locale } = useI18n();
  const doc = getDeleteAccountGuide(lang.value);

  return (
    <>
      <LegalDocumentView doc={doc} />
      {/* Shortcut to the in-app delete request screen (requires sign-in). */}
      <div class="content-page" style={{ paddingTop: 0 }}>
        <Link href={localePath(locale, "/account/security")} class="btn btn-primary">
          {tStatic(locale, "account.deleteMyAccount")}
        </Link>
      </div>
      <JsonLd data={deleteAccountHowToJsonLd(lang.value)} />
    </>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const doc = getDeleteAccountGuide(lang);
  const title = `${doc.title} — ${settings.business_name}`;
  const description = doc.intro || doc.title;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { property: "og:url", content: url.href },
        { name: "twitter:card", content: "summary" },
        { name: "twitter:title", content: title },
        { name: "twitter:description", content: description },
      ],
      links: publicSeoLinks(url.origin, "/delete-account", lang),
    },
    settings,
  );
};
