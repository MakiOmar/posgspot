import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { CommunityPostDetail, CommunityPostSummary } from "~/lib/types";

type MediaProps = {
  media?: CommunityPostDetail["media"];
};

export const CommunityMediaGallery = component$<MediaProps>((props) => {
  const { locale } = useI18n();
  const media = props.media ?? [];
  if (media.length === 0) return null;

  return (
    <section class="community-gallery" aria-labelledby="community-gallery-heading">
      <h2 id="community-gallery-heading">{tStatic(locale, "community.gallery")}</h2>
      <ul class="community-gallery__grid">
        {media.map((item, index) => (
          <li key={`${item.url}-${index}`}>
            {item.kind === "video" ? (
              <a href={item.url} target="_blank" rel="noopener noreferrer" class="community-gallery__video">
                {item.caption || tStatic(locale, "community.gallery")}
              </a>
            ) : (
              <a href={item.url} target="_blank" rel="noopener noreferrer">
                <img src={item.url} alt={item.caption || ""} width={640} height={360} loading="lazy" />
              </a>
            )}
            {item.caption ? <p class="footer-muted">{item.caption}</p> : null}
          </li>
        ))}
      </ul>
    </section>
  );
});

type RelatedProps = {
  posts?: CommunityPostSummary[];
  detailBase: string;
  titleKey?: string;
};

export const CommunityRelatedPosts = component$<RelatedProps>((props) => {
  const { locale } = useI18n();
  const posts = props.posts ?? [];
  if (posts.length === 0) return null;

  return (
    <section class="community-related" aria-labelledby="community-related-heading">
      <h2 id="community-related-heading">
        {tStatic(locale, props.titleKey || "community.related")}
      </h2>
      <ul class="community-post-grid">
        {posts.map((post) => (
          <li key={post.id} class="community-post-card">
            {post.cover_url ? (
              <Link
                href={localePath(locale, `${props.detailBase}/${post.slug}`)}
                class="community-post-card__cover"
              >
                <img src={post.cover_url} alt="" width={640} height={360} loading="lazy" />
              </Link>
            ) : null}
            <div class="community-post-card__body">
              <h3>
                <Link href={localePath(locale, `${props.detailBase}/${post.slug}`)}>
                  {post.title}
                </Link>
              </h3>
              {post.excerpt ? <p class="footer-muted">{post.excerpt}</p> : null}
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
});

type FactsProps = {
  post: CommunityPostDetail;
};

/** Structured tournament/event fact list shown under the title. */
export const CommunityPostFacts = component$<FactsProps>((props) => {
  const { locale } = useI18n();
  const post = props.post;
  const rows: Array<{ label: string; value: string }> = [];

  if (post.game_title) {
    rows.push({ label: tStatic(locale, "community.game"), value: post.game_title });
  }
  if (post.location?.name) {
    rows.push({ label: tStatic(locale, "community.location"), value: post.location.name });
  }
  if (post.prize_pool) {
    rows.push({ label: tStatic(locale, "community.prizePool"), value: post.prize_pool });
  }
  if (post.entry_fee) {
    rows.push({ label: tStatic(locale, "community.entryFee"), value: post.entry_fee });
  }
  if (post.available_spots != null) {
    rows.push({
      label: tStatic(locale, "community.spots"),
      value: String(post.available_spots),
    });
  }
  if (post.winner) {
    rows.push({ label: tStatic(locale, "community.winner"), value: post.winner });
  }

  if (rows.length === 0) return null;

  return (
    <dl class="community-facts">
      {rows.map((row) => (
        <div key={row.label}>
          <dt>{row.label}</dt>
          <dd>{row.value}</dd>
        </div>
      ))}
    </dl>
  );
});

type HtmlSectionProps = {
  titleKey: string;
  html?: string | null;
};

export const CommunityHtmlSection = component$<HtmlSectionProps>((props) => {
  const { locale } = useI18n();
  const html = (props.html || "").trim();
  if (!html) return null;

  return (
    <section class="community-html-section">
      <h2>{tStatic(locale, props.titleKey)}</h2>
      <SanitizedHtml html={html} class="content-prose" />
    </section>
  );
});
