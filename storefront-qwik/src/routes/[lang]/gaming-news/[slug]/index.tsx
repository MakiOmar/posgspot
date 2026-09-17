import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { ApiError, fetchCommunityPost } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CommunityPostDetail } from "~/lib/types";
import { useSiteSettings } from "~/routes/[lang]/layout";

function formatWhen(value: string | null, locale: string): string {
  if (!value) return "";
  const d = new Date(value);
  return Number.isNaN(d.getTime())
    ? value
    : d.toLocaleDateString(locale === "ar" ? "ar-EG" : "en-EG", {
        year: "numeric",
        month: "long",
        day: "numeric",
      });
}

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
    <article class="content-page community-page community-detail">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <Link href={localePath(locale, "/gaming-news")}>
          {tStatic(locale, "community.newsTitle")}
        </Link>
        <span aria-hidden="true">›</span>
        <span>{post.title}</span>
      </nav>

      {post.cover_url ? (
        <div class="community-detail__cover">
          <img src={post.cover_url} alt="" />
        </div>
      ) : null}

      <h1 class="content-title">{post.title}</h1>
      {post.published_at ? (
        <p class="community-post-card__meta">{formatWhen(post.published_at, locale)}</p>
      ) : null}
      {post.excerpt ? <p class="content-lead">{post.excerpt}</p> : null}
      <SanitizedHtml html={post.body} class="content-prose community-detail__body" />

      <p>
        <Link class="link-accent" href={localePath(locale, "/gaming-news")}>
          ← {tStatic(locale, "community.back")}
        </Link>
      </p>
    </article>
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

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "article" },
        { property: "og:url", content: url.href },
        { name: "twitter:card", content: "summary_large_image" },
        { name: "twitter:title", content: title },
        { name: "twitter:description", content: description },
      ],
      links: publicSeoLinks(url.origin, `/gaming-news/${params.slug || ""}`, lang),
    },
    settings,
  );
};
