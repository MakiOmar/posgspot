import { component$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { CommunityNewsListPage } from "~/components/community/community-news-list-page";
import { ApiError, fetchCommunityPosts } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CommunityPostSummary } from "~/lib/types";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useGamingNewsPage = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.community?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  try {
    const { data } = await fetchCommunityPosts({ type: "news" }, locale);
    return { posts: data as CommunityPostSummary[], unavailable: false };
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      return { posts: [] as CommunityPostSummary[], unavailable: true };
    }
    throw e;
  }
});

export default component$(() => {
  const page = useGamingNewsPage();
  return (
    <CommunityNewsListPage posts={page.value.posts} unavailable={page.value.unavailable} />
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = `${tStatic(lang, "community.newsTitle")} — ${settings.business_name}`;
  const description = tStatic(lang, "community.newsLead");

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
      links: publicSeoLinks(url.origin, "/gaming-news", lang),
    },
    settings,
  );
};
