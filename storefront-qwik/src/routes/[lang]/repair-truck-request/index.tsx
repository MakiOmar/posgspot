import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

/** Coming-soon placeholder for on-site repair truck booking. */
export default component$(() => {
  const { locale } = useI18n();

  return (
    <article class="content-page coming-soon-page">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">/</span>
        <span>{tStatic(locale, "nav.repairTruck")}</span>
      </nav>

      <p class="coming-soon-badge">{tStatic(locale, "repairTruck.badge")}</p>
      <h1 class="content-title">{tStatic(locale, "repairTruck.title")}</h1>
      <p class="content-prose">{tStatic(locale, "repairTruck.lead")}</p>
      <p class="footer-muted">{tStatic(locale, "repairTruck.hint")}</p>

      <div class="coming-soon-actions">
        <Link class="btn btn-secondary" href={localePath(locale, "/repair-status")}>
          {tStatic(locale, "nav.trackRepairs")}
        </Link>
        <Link class="btn btn-primary" href={localePath(locale, "/contact")}>
          {tStatic(locale, "nav.leaveMessage")}
        </Link>
      </div>
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const title = `${tStatic(lang, "repairTruck.title")} — ${settings.business_name}`;
  const description = tStatic(lang, "repairTruck.lead");

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { name: "robots", content: "noindex, follow" },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { property: "og:url", content: url.href },
      ],
      links: publicSeoLinks(url.origin, "/repair-truck-request", lang),
    },
    settings,
  );
};
