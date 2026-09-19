import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { formatCommunityWhen } from "~/components/community/community-dates";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { CommunityPostSummary } from "~/lib/types";

type Props = {
  posts: CommunityPostSummary[];
  unavailable?: boolean;
};

/**
 * Gaming news index: Featured hero (newest featured) + Latest cards
 * with the featured hero deduped from Latest.
 */
export const CommunityNewsListPage = component$<Props>((props) => {
  const { locale } = useI18n();
  const posts = props.posts ?? [];

  const featuredCandidates = posts.filter((p) => p.is_featured);
  const hero =
    featuredCandidates.length > 0
      ? [...featuredCandidates].sort((a, b) => {
          const ta = a.published_at ? new Date(a.published_at).getTime() : 0;
          const tb = b.published_at ? new Date(b.published_at).getTime() : 0;
          return tb - ta;
        })[0]
      : null;

  const latest = hero ? posts.filter((p) => p.id !== hero.id) : posts;

  return (
    <article class="content-page community-page community-news-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "community.newsTitle")}</span>
      </nav>

      <h1 class="content-title">{tStatic(locale, "community.newsTitle")}</h1>
      <p class="content-lead">{tStatic(locale, "community.newsLead")}</p>

      {props.unavailable ? (
        <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
      ) : null}

      {!props.unavailable && posts.length === 0 ? (
        <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
      ) : null}

      {hero ? (
        <section class="community-news-featured" aria-labelledby="community-featured-heading">
          <p class="community-news-featured__eyebrow" id="community-featured-heading">
            {tStatic(locale, "community.featured")}
          </p>
          <Link
            href={localePath(locale, `/gaming-news/${hero.slug}`)}
            class="community-news-featured__card"
          >
            {hero.cover_url ? (
              <img src={hero.cover_url} alt="" width={1200} height={675} />
            ) : null}
            <div class="community-news-featured__body">
              {hero.published_at ? (
                <p class="community-post-card__meta">
                  {formatCommunityWhen(hero.published_at, locale)}
                </p>
              ) : null}
              <h2>{hero.title}</h2>
              {hero.excerpt ? <p>{hero.excerpt}</p> : null}
              <span class="link-accent">{tStatic(locale, "community.readMore")}</span>
            </div>
          </Link>
        </section>
      ) : null}

      {latest.length > 0 ? (
        <section class="community-news-latest" aria-labelledby="community-latest-heading">
          <h2 id="community-latest-heading" class="community-section-title">
            {tStatic(locale, "community.latest")}
          </h2>
          <ul class="community-post-grid">
            {latest.map((post) => (
              <li key={post.id} class="community-post-card">
                {post.cover_url ? (
                  <Link
                    href={localePath(locale, `/gaming-news/${post.slug}`)}
                    class="community-post-card__cover"
                  >
                    <img src={post.cover_url} alt="" width={640} height={360} loading="lazy" />
                  </Link>
                ) : null}
                <div class="community-post-card__body">
                  {post.published_at ? (
                    <p class="community-post-card__meta">
                      {formatCommunityWhen(post.published_at, locale)}
                    </p>
                  ) : null}
                  <h3>
                    <Link href={localePath(locale, `/gaming-news/${post.slug}`)}>{post.title}</Link>
                  </h3>
                  {post.excerpt ? <p class="footer-muted">{post.excerpt}</p> : null}
                  <Link
                    class="link-accent"
                    href={localePath(locale, `/gaming-news/${post.slug}`)}
                  >
                    {tStatic(locale, "community.readMore")}
                  </Link>
                </div>
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </article>
  );
});
