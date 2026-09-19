import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import {
  formatCommunityRange,
  formatCommunityWhen,
} from "~/components/community/community-dates";
import { ApiError, fetchCommunityPosts } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { CommunityPostSummary, CommunityPostType } from "~/lib/types";

type Props = {
  type: CommunityPostType;
  detailBase: string;
  scoped: boolean;
  titleKey: string;
  leadKey: string;
  /** SSR posts (upcoming for scoped types). */
  initialPosts?: CommunityPostSummary[];
};

/** Shared community list with optional upcoming/previous scope. */
export const CommunityPostListPage = component$<Props>((props) => {
  const { locale } = useI18n();
  const scope = useSignal<"upcoming" | "previous">("upcoming");
  const posts = useSignal<CommunityPostSummary[]>(props.initialPosts ?? []);
  const loading = useSignal(props.initialPosts === undefined);
  const unavailable = useSignal(false);
  const skipFirstScopedFetch = useSignal(Boolean(props.initialPosts && props.scoped));

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => scope.value);

    if (!props.scoped) {
      // Non-scoped lists rely on SSR `initialPosts` when provided.
      if (props.initialPosts !== undefined) {
        posts.value = props.initialPosts;
        loading.value = false;
        return;
      }
    } else if (skipFirstScopedFetch.value && scope.value === "upcoming") {
      skipFirstScopedFetch.value = false;
      loading.value = false;
      return;
    }

    loading.value = true;
    unavailable.value = false;
    try {
      const { data } = await fetchCommunityPosts(
        {
          type: props.type,
          ...(props.scoped ? { scope: scope.value } : {}),
        },
        locale,
      );
      posts.value = data;
    } catch (e) {
      posts.value = [];
      if (e instanceof ApiError && e.status === 404) {
        unavailable.value = true;
      }
    } finally {
      loading.value = false;
    }
  });

  return (
    <article class="content-page community-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, props.titleKey)}</span>
      </nav>

      <h1 class="content-title">{tStatic(locale, props.titleKey)}</h1>
      <p class="content-lead">{tStatic(locale, props.leadKey)}</p>

      {props.scoped ? (
        <div class="community-scope-tabs" role="tablist">
          <button
            type="button"
            role="tab"
            class={scope.value === "upcoming" ? "is-active" : undefined}
            aria-selected={scope.value === "upcoming"}
            onClick$={() => {
              scope.value = "upcoming";
            }}
          >
            {tStatic(locale, "community.upcoming")}
          </button>
          <button
            type="button"
            role="tab"
            class={scope.value === "previous" ? "is-active" : undefined}
            aria-selected={scope.value === "previous"}
            onClick$={() => {
              scope.value = "previous";
            }}
          >
            {tStatic(locale, "community.previous")}
          </button>
        </div>
      ) : null}

      {loading.value ? <p class="footer-muted">…</p> : null}

      {!loading.value && unavailable.value ? (
        <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
      ) : null}

      {!loading.value && !unavailable.value && posts.value.length === 0 ? (
        <p class="footer-muted">{tStatic(locale, "community.empty")}</p>
      ) : null}

      {!loading.value && posts.value.length > 0 ? (
        <ul class="community-post-grid">
          {posts.value.map((post) => (
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
                {post.game_title ? (
                  <p class="community-post-card__game">{post.game_title}</p>
                ) : null}
                {formatCommunityRange(post.starts_at, post.ends_at, locale) ? (
                  <p class="community-post-card__meta">
                    {formatCommunityRange(post.starts_at, post.ends_at, locale)}
                  </p>
                ) : post.published_at ? (
                  <p class="community-post-card__meta">
                    {formatCommunityWhen(post.published_at, locale)}
                  </p>
                ) : null}
                {post.location?.name ? (
                  <p class="community-post-card__meta">{post.location.name}</p>
                ) : null}
                <h2>
                  <Link href={localePath(locale, `${props.detailBase}/${post.slug}`)}>
                    {post.title}
                  </Link>
                </h2>
                {post.prize_pool ? (
                  <p class="community-post-card__meta">
                    {tStatic(locale, "community.prizePool")}: {post.prize_pool}
                  </p>
                ) : null}
                {post.excerpt ? <p class="footer-muted">{post.excerpt}</p> : null}
                <Link
                  class="link-accent"
                  href={localePath(locale, `${props.detailBase}/${post.slug}`)}
                >
                  {tStatic(locale, "community.readMore")}
                </Link>
              </div>
            </li>
          ))}
        </ul>
      ) : null}
    </article>
  );
});
