import { component$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { AboutTimeline } from "~/components/content/about-timeline";
import { AboutTeam, type AboutTeamMember } from "~/components/content/about-team";
import { JsonLd } from "~/components/seo/json-ld";
import { getAboutContent } from "~/lib/about-content";
import { getAboutTimeline } from "~/lib/about-timeline";
import { fetchSettings } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

/** Full settings (not shell) so About team photos are present. */
export const useAboutTeam = routeLoader$(async ({ params }): Promise<AboutTeamMember[]> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchSettings(locale);
    return data.about?.team ?? [];
  } catch {
    return [];
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const team = useAboutTeam();
  const lang = useLangParam();
  const name = settings.value.business_name;
  const content = getAboutContent(lang.value);

  return (
    <article class="content-page about-page">
      <section class="about-hero">
        <p class="about-hero-kicker">{content.kicker}</p>
        <h1 class="about-hero-title">{name}</h1>
        <p class="about-hero-lead">{content.lead}</p>
      </section>

      <section class="about-section">
        <div class="about-split">
          <div>
            <h2 class="content-section-title">{content.whoWeAreTitle}</h2>
            <p class="content-prose">
              {name} {content.whoWeAreBody}
            </p>
          </div>
          <div class="about-stats">
            <div class="about-stat-card">
              <strong>5+</strong>
              <span>{content.yearsLabel}</span>
            </div>
            <div class="about-stat-card">
              <strong>2K+</strong>
              <span>{content.customersLabel}</span>
            </div>
          </div>
        </div>
      </section>

      <section class="about-section about-vision">
        <h2 class="content-section-title">{content.visionTitle}</h2>
        <div class="about-vision-grid">
          {content.visionItems.map((item) => (
            <div key={item.title} class="about-vision-card">
              <h3>{item.title}</h3>
              <p>{item.text}</p>
            </div>
          ))}
        </div>
      </section>

      <section class="about-section about-history">
        <h2 class="content-section-title content-section-title--center">{content.historyTitle}</h2>
        <p class="content-prose about-intro about-intro--center">{content.historyIntro}</p>
        <AboutTimeline items={getAboutTimeline(lang.value)} />
      </section>

      <AboutTeam members={team.value} />

      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "Organization",
          name,
          url: "/",
        }}
      />
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const title = tStatic(lang, "seo.aboutTitle", { businessName: settings.business_name });
  const description = tStatic(lang, "seo.aboutDescription", {
    businessName: settings.business_name,
  });

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
      links: publicSeoLinks(url.origin, "/about", lang),
    },
    settings,
  );
};
