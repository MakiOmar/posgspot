import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import {
  CommunityHtmlSection,
  CommunityMediaGallery,
  CommunityPostFacts,
  CommunityRelatedPosts,
} from "~/components/community/community-detail-sections";
import { formatCommunityRange } from "~/components/community/community-dates";
import { CommunityRegistrationForm } from "~/components/community/community-registration-form";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { ApiError, fetchCommunityPost, fetchPhoneCountries } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { PhoneCountry } from "~/lib/phone-validation";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CommunityPostDetail } from "~/lib/types";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useEventPost = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.community?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  let phoneCountries: PhoneCountry[] = [];
  try {
    const { data } = await fetchPhoneCountries();
    phoneCountries = data;
  } catch {
    phoneCountries = [];
  }

  try {
    const { data } = await fetchCommunityPost(params.slug || "", locale);
    if (data.type !== "event") {
      throw redirect(302, localePath(locale, "/events"));
    }
    return {
      post: data as CommunityPostDetail,
      notFound: false as const,
      phoneCountries,
    };
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      return { post: null, notFound: true as const, phoneCountries };
    }
    throw e;
  }
});

export default component$(() => {
  const page = useEventPost();
  const { locale } = useI18n();
  const post = page.value.post;

  if (page.value.notFound || !post) {
    return (
      <article class="content-page community-page">
        <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
        <Link class="btn btn-secondary" href={localePath(locale, "/events")}>
          {tStatic(locale, "community.back")}
        </Link>
      </article>
    );
  }

  const range = formatCommunityRange(post.starts_at, post.ends_at, locale);
  const regOpen = Boolean(post.registration_open);
  const mode = post.registration_mode || "off";

  return (
    <article class="content-page community-page community-detail">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <Link href={localePath(locale, "/events")}>{tStatic(locale, "community.eventsTitle")}</Link>
        <span aria-hidden="true">›</span>
        <span>{post.title}</span>
      </nav>

      {post.cover_url ? (
        <div class="community-detail__cover">
          <img src={post.cover_url} alt="" width={1200} height={675} />
        </div>
      ) : null}

      <h1 class="content-title">{post.title}</h1>
      {range ? <p class="community-post-card__meta">{range}</p> : null}
      {post.excerpt ? <p class="content-lead">{post.excerpt}</p> : null}
      <CommunityPostFacts post={post} />
      <SanitizedHtml html={post.body} class="content-prose community-detail__body" />

      <CommunityHtmlSection titleKey="community.highlights" html={post.highlights} />
      <CommunityHtmlSection titleKey="community.recap" html={post.recap} />
      <CommunityMediaGallery media={post.media} />

      {regOpen && mode === "external" && post.registration_url ? (
        <section class="community-register">
          {post.registration_details ? (
            <p class="footer-muted">{post.registration_details}</p>
          ) : null}
          <a
            class="btn btn-primary"
            href={post.registration_url}
            target="_blank"
            rel="noopener noreferrer"
          >
            {tStatic(locale, "community.registerNow")}
          </a>
        </section>
      ) : null}

      {regOpen && mode === "internal" ? (
        <CommunityRegistrationForm
          slug={post.slug}
          registrationDetails={post.registration_details}
          phoneCountries={page.value.phoneCountries}
        />
      ) : null}

      <CommunityRelatedPosts posts={post.related_posts} detailBase="/events" />

      <p>
        <Link class="link-accent" href={localePath(locale, "/events")}>
          ← {tStatic(locale, "community.back")}
        </Link>
      </p>
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const page = resolveValue(useEventPost);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = page.post
    ? `${page.post.title} — ${settings.business_name}`
    : `${tStatic(lang, "community.eventsTitle")} — ${settings.business_name}`;
  const description = page.post?.excerpt || tStatic(lang, "community.eventsLead");
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
      links: publicSeoLinks(url.origin, `/events/${params.slug || ""}`, lang),
    },
    settings,
  );
};
