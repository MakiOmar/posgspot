import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { ApiError, fetchCommunityPosts } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { CommunityPostSummary, CommunityPostType } from "~/lib/types";

function formatWhen(value: string | null, locale: string): string {
  if (!value) return "";
  const d = new Date(value);
  return Number.isNaN(d.getTime())
    ? value
    : d.toLocaleDateString(locale === "ar" ? "ar-EG" : "en-EG", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
}

type Props = {
  type: CommunityPostType;
  /** Base path without locale, e.g. /tournaments */
  detailBase: string;
  /** When true, show Upcoming / Previous tabs. */
  scoped: boolean;
  titleKey: string;
  leadKey: string;
};

/** Shared community list with optional upcoming/previous scope. */
export const CommunityPostListPage = component$<Props>((props) => {
  const { locale } = useI18n();
  const scope = useSignal<"upcoming" | "previous">("upcoming");
  const posts = useSignal<CommunityPostSummary[]>([]);
  const loading = useSignal(true);
  const unavailable = useSignal(false);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => scope.value);
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
                  <img src={post.cover_url} alt="" loading="lazy" />
                </Link>
              ) : null}
              <div class="community-post-card__body">
                {post.starts_at ? (
                  <p class="community-post-card__meta">{formatWhen(post.starts_at, locale)}</p>
                ) : post.published_at ? (
                  <p class="community-post-card__meta">{formatWhen(post.published_at, locale)}</p>
                ) : null}
                <h2>
                  <Link href={localePath(locale, `${props.detailBase}/${post.slug}`)}>
                    {post.title}
                  </Link>
                </h2>
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
