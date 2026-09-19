import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { formatCommunityWhen } from "~/components/community/community-dates";
import { CommunityFeaturedTiles } from "~/components/community/community-featured-tiles";
import { CommunityWithSidebar } from "~/components/community/community-with-sidebar";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { CommunityPostSummary } from "~/lib/types";

type Props = {
  posts: CommunityPostSummary[];
  upcomingTournaments: CommunityPostSummary[];
  upcomingEvents: CommunityPostSummary[];
  unavailable?: boolean;
  query?: string;
};

/**
 * Gaming news index: promo-tile featured mosaic, latest cards, sidebar widgets.
 */
export const CommunityNewsListPage = component$<Props>((props) => {
  const { locale } = useI18n();
  const posts = props.posts ?? [];
  const q = (props.query || "").trim().toLowerCase();

  const filtered = q
    ? posts.filter((p) => {
        const hay = `${p.title} ${p.excerpt || ""}`.toLowerCase();
        return hay.includes(q);
      })
    : posts;

  const featuredCandidates = filtered.filter((p) => p.is_featured);
  const featuredSorted = [...featuredCandidates].sort((a, b) => {
    const ta = a.published_at ? new Date(a.published_at).getTime() : 0;
    const tb = b.published_at ? new Date(b.published_at).getTime() : 0;
    return tb - ta;
  });

  const featuredIds = new Set(featuredSorted.map((p) => p.id));
  const fillers = filtered.filter((p) => !featuredIds.has(p.id));
  const mosaic = q ? [] : [...featuredSorted, ...fillers].slice(0, 4);
  const mosaicIds = new Set(mosaic.map((p) => p.id));
  const latestList = q ? filtered : filtered.filter((p) => !mosaicIds.has(p.id));

  return (
    <div class="community-news-layout">
      <PageTitleBar
        title={tStatic(locale, "community.newsTitle")}
        crumbs={[{ label: tStatic(locale, "community.newsTitle") }]}
      />

      <CommunityWithSidebar
        upcomingTournaments={props.upcomingTournaments}
        upcomingEvents={props.upcomingEvents}
        initialQuery={props.query}
      >
        <p class="community-page-intro">{tStatic(locale, "community.newsLead")}</p>
        {props.unavailable ? (
          <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
        ) : null}

        {!props.unavailable && filtered.length === 0 ? (
          <p class="footer-muted">
            {q ? tStatic(locale, "community.searchEmpty") : tStatic(locale, "community.empty")}
          </p>
        ) : null}

        {mosaic.length > 0 ? <CommunityFeaturedTiles posts={mosaic} /> : null}

        {latestList.length > 0 ? (
          <section class="community-news-latest" aria-labelledby="community-latest-heading">
            <h2 id="community-latest-heading" class="community-section-title">
              {q ? tStatic(locale, "community.searchResults") : tStatic(locale, "community.latest")}
            </h2>
            <ul class="community-post-grid">
              {latestList.map((post) => (
                <li key={post.id} class="community-post-card">
                  {post.cover_url ? (
                    <Link
                      href={localePath(locale, `/gaming-news/${post.slug}`)}
                      class="community-post-card__cover"
                      prefetch={false}
                    >
                      <img
                        src={post.cover_url}
                        alt=""
                        width={640}
                        height={360}
                        loading="lazy"
                      />
                    </Link>
                  ) : null}
                  <div class="community-post-card__body">
                    {post.published_at ? (
                      <p class="community-post-card__meta">
                        {formatCommunityWhen(post.published_at, locale)}
                      </p>
                    ) : null}
                    <h3>
                      <Link
                        href={localePath(locale, `/gaming-news/${post.slug}`)}
                        prefetch={false}
                      >
                        {post.title}
                      </Link>
                    </h3>
                    {post.excerpt ? <p class="footer-muted">{post.excerpt}</p> : null}
                    <Link
                      class="link-accent"
                      href={localePath(locale, `/gaming-news/${post.slug}`)}
                      prefetch={false}
                    >
                      {tStatic(locale, "community.readMore")}
                    </Link>
                  </div>
                </li>
              ))}
            </ul>
          </section>
        ) : null}
      </CommunityWithSidebar>
    </div>
  );
});
