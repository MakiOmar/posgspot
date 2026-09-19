import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import {
  CommunityMediaGallery,
  CommunityRelatedPosts,
} from "~/components/community/community-detail-sections";
import { formatCommunityWhen } from "~/components/community/community-dates";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { ApiError, fetchCommunityPost } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CommunityPostDetail } from "~/lib/types";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useNewsPost = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.community?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  try {
    const { data } = await fetchCommunityPost(params.slug || "", locale);
    if (data.type !== "news") {
      throw redirect(302, localePath(locale, "/gaming-news"));
    }
    return { post: data as CommunityPostDetail, notFound: false as const };
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      return { post: null, notFound: true as const };
    }
    throw e;
  }
});

export default component$(() => {
  const page = useNewsPost();
  const { locale } = useI18n();
  const post = page.value.post;

  if (page.value.notFound || !post) {
    return (
      <article class="content-page community-page">
        <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
        <Link class="btn btn-secondary" href={localePath(locale, "/gaming-news")}>
          {tStatic(locale, "community.back")}
        </Link>
      </article>
    );
  }

  return (
    <div class="community-detail-layout">
      <PageTitleBar
        title={post.title}
        lead={post.excerpt || undefined}
        crumbs={[
          { label: tStatic(locale, "community.newsTitle"), href: "/gaming-news" },
          { label: post.title },
        ]}
      />
      <article class="content-page community-page community-detail">
      {post.cover_url ? (
        <div class="community-detail__cover">
          <img src={post.cover_url} alt="" width={1200} height={675} />
        </div>
      ) : null}

      {post.published_at ? (
        <p class="community-post-card__meta">{formatCommunityWhen(post.published_at, locale)}</p>
      ) : null}
      <SanitizedHtml html={post.body} class="content-prose community-detail__body" />

      <CommunityMediaGallery media={post.media} />
      <CommunityRelatedPosts posts={post.related_posts} detailBase="/gaming-news" />

      <p>
        <Link class="link-accent" href={localePath(locale, "/gaming-news")}>
          ← {tStatic(locale, "community.back")}
        </Link>
      </p>
    </article>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const page = resolveValue(useNewsPost);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = page.post
    ? `${page.post.title} — ${settings.business_name}`
    : `${tStatic(lang, "community.newsTitle")} — ${settings.business_name}`;
  const description = page.post?.excerpt || tStatic(lang, "community.newsLead");
  const ogImage = page.post?.cover_url || undefined;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "article" },
        { property: "og:url", content: url.href },
        ...(ogImage ? [{ property: "og:image", content: ogImage }] : []),
        { name: "twitter:card", content: "summary_large_image" },
        { name: "twitter:title", content: title },
        { name: "twitter:description", content: description },
        ...(ogImage ? [{ name: "twitter:image", content: ogImage }] : []),
      ],
      links: publicSeoLinks(url.origin, `/gaming-news/${params.slug || ""}`, lang),
    },
    settings,
  );
};
