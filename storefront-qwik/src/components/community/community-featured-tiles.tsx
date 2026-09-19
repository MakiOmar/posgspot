import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { CommunityPostSummary } from "~/lib/types";

type Props = {
  posts: CommunityPostSummary[];
  detailBase?: string;
};

/**
 * Featured community posts in the homepage promo-tiles mosaic layout.
 * Slot order: main (tall), wide, then two small tiles.
 */
export const CommunityFeaturedTiles = component$<Props>((props) => {
  const { locale } = useI18n();
  const base = props.detailBase || "/gaming-news";
  const posts = props.posts.slice(0, 4);

  if (posts.length === 0) {
    return null;
  }

  return (
    <section class="home-promo-tiles community-featured-tiles" aria-labelledby="community-featured-heading">
      <p class="home-promo-tiles__eyebrow" id="community-featured-heading">
        {tStatic(locale, "community.featured")}
      </p>
      <div class="home-promo-tiles__grid">
        {posts.map((post, i) => (
          <Link
            key={post.id}
            href={localePath(locale, `${base}/${post.slug}`)}
            class={[
              "home-promo-tiles__tile",
              i === 0 ? "home-promo-tiles__tile--main" : "",
              i === 1 ? "home-promo-tiles__tile--wide" : "",
            ]
              .filter(Boolean)
              .join(" ")}
          >
            {post.cover_url ? (
              <img
                class="home-promo-tiles__img"
                src={post.cover_url}
                alt=""
                width={i === 0 ? 800 : 400}
                height={i === 0 ? 600 : 300}
                loading={i === 0 ? "eager" : "lazy"}
              />
            ) : (
              <span class="community-featured-tiles__fallback" aria-hidden="true" />
            )}
            <span class="home-promo-tiles__cta community-featured-tiles__cta">
              {post.title}
            </span>
          </Link>
        ))}
      </div>
    </section>
  );
});
