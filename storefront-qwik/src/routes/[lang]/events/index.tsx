import { component$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { CommunityPostListPage } from "~/components/community/community-post-list-page";
import { ApiError, fetchCommunityPosts } from "~/lib/api";
import { loadCommunitySidebarLists } from "~/lib/community-sidebar-data";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CommunityPostSummary } from "~/lib/types";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useEventsPage = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.community?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  const sidebar = await loadCommunitySidebarLists(locale);

  try {
    const { data } = await fetchCommunityPosts({ type: "event", scope: "upcoming" }, locale);
    return { posts: data as CommunityPostSummary[], ...sidebar };
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      return { posts: [] as CommunityPostSummary[], ...sidebar };
    }
    throw e;
  }
});

export default component$(() => {
  const page = useEventsPage();
  return (
    <CommunityPostListPage
      type="event"
      detailBase="/events"
      scoped
      titleKey="community.eventsTitle"
      leadKey="community.eventsLead"
      initialPosts={page.value.posts}
      upcomingTournaments={page.value.upcomingTournaments}
      upcomingEvents={page.value.upcomingEvents}
    />
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = `${tStatic(lang, "community.eventsTitle")} — ${settings.business_name}`;
  const description = tStatic(lang, "community.eventsLead");

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
      links: publicSeoLinks(url.origin, "/events", lang),
    },
    settings,
  );
};
