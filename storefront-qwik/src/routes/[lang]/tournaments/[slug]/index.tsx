import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import {
  CommunityCoverImage,
  CommunityHtmlSection,
  CommunityMediaGallery,
  CommunityPostFacts,
  CommunityRelatedPosts,
} from "~/components/community/community-detail-sections";
import { formatCommunityRange } from "~/components/community/community-dates";
import { CommunityRegistrationForm } from "~/components/community/community-registration-form";
import { CommunityWithSidebar } from "~/components/community/community-with-sidebar";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { fetchPhoneCountries } from "~/lib/api";
import { loadCommunityDetailPage } from "~/lib/community-detail-load";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { PhoneCountry } from "~/lib/phone-validation";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useTournamentPost = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.community?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  let phoneCountries: PhoneCountry[] = [];
  const loaded = await loadCommunityDetailPage(locale, params.slug || "", "tournament");
  if (loaded.wrongType) {
    throw redirect(302, localePath(locale, "/tournaments"));
  }
  // Phone countries only needed for internal registration forms.
  if (loaded.post?.registration_mode === "internal") {
    try {
      const { data } = await fetchPhoneCountries();
      phoneCountries = data;
    } catch {
      phoneCountries = [];
    }
  }
  return {
    post: loaded.post,
    notFound: loaded.notFound,
    upcomingTournaments: loaded.upcomingTournaments,
    upcomingEvents: loaded.upcomingEvents,
    phoneCountries,
  };
});

export default component$(() => {
  const page = useTournamentPost();
  const { locale } = useI18n();
  const post = page.value.post;

  if (page.value.notFound || !post) {
    return (
      <div class="community-detail-layout">
        <CommunityWithSidebar
          upcomingTournaments={page.value.upcomingTournaments}
          upcomingEvents={page.value.upcomingEvents}
        >
          <article class="community-page">
            <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
            <Link class="btn btn-secondary" href={localePath(locale, "/tournaments")}>
              {tStatic(locale, "community.back")}
            </Link>
          </article>
        </CommunityWithSidebar>
      </div>
    );
  }

  const range = formatCommunityRange(post.starts_at, post.ends_at, locale);
  const regOpen = Boolean(post.registration_open);
  const mode = post.registration_mode || "off";

  return (
    <div class="community-detail-layout">
      <PageTitleBar
        title={post.title}
        crumbs={[
          { label: tStatic(locale, "community.tournamentsTitle"), href: "/tournaments" },
          { label: post.title },
        ]}
      />
      <CommunityWithSidebar
        upcomingTournaments={page.value.upcomingTournaments}
        upcomingEvents={page.value.upcomingEvents}
      >
        <article class="community-page community-detail">
          {post.cover_url ? <CommunityCoverImage src={post.cover_url} alt={post.title} /> : null}

          {range ? <p class="community-post-card__meta">{range}</p> : null}
          {post.excerpt ? <p class="community-page-intro">{post.excerpt}</p> : null}
          <CommunityPostFacts post={post} />
          <SanitizedHtml html={post.body} class="content-prose community-detail__body" />

          <CommunityHtmlSection titleKey="community.rules" html={post.rules} />
          <CommunityHtmlSection titleKey="community.results" html={post.results} />
          <CommunityHtmlSection titleKey="community.highlights" html={post.highlights} />
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

          <CommunityRelatedPosts posts={post.related_posts} detailBase="/tournaments" />

          <p>
            <Link class="link-accent" href={localePath(locale, "/tournaments")}>
              ← {tStatic(locale, "community.back")}
            </Link>
          </p>
        </article>
      </CommunityWithSidebar>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const page = resolveValue(useTournamentPost);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = page.post
    ? `${page.post.title} — ${settings.business_name}`
    : `${tStatic(lang, "community.tournamentsTitle")} — ${settings.business_name}`;
  const description = page.post?.excerpt || tStatic(lang, "community.tournamentsLead");
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
      links: publicSeoLinks(url.origin, `/tournaments/${params.slug || ""}`, lang),
    },
    settings,
  );
};
