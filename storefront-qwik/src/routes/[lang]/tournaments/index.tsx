import { component$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { CommunityPostListPage } from "~/components/community/community-post-list-page";
import { loadCommunityScopedListPage } from "~/lib/community-sidebar-data";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useTournamentsPage = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.community?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  return loadCommunityScopedListPage(locale, "tournament");
});

export default component$(() => {
  const page = useTournamentsPage();
  return (
    <CommunityPostListPage
      type="tournament"
      detailBase="/tournaments"
      scoped
      titleKey="community.tournamentsTitle"
      leadKey="community.tournamentsLead"
      initialPosts={page.value.posts}
      upcomingTournaments={page.value.upcomingTournaments}
      upcomingEvents={page.value.upcomingEvents}
    />
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = `${tStatic(lang, "community.tournamentsTitle")} — ${settings.business_name}`;
  const description = tStatic(lang, "community.tournamentsLead");

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
      links: publicSeoLinks(url.origin, "/tournaments", lang),
    },
    settings,
  );
};
