import { component$, useSignal } from "@builder.io/qwik";
import { Link, useNavigate } from "@builder.io/qwik-city";
import { TurnstileWidget } from "~/components/forms/turnstile-widget";
import { ApiError, subscribeNewsletter } from "~/lib/api";
import { formatCommunityWhen } from "~/components/community/community-dates";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import type { CommunityPostSummary } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/[lang]/layout";

type Props = {
  upcomingTournaments: CommunityPostSummary[];
  upcomingEvents: CommunityPostSummary[];
  initialQuery?: string;
};

/** News index sidebar: newsletter, community search, upcoming events/tournaments. */
export const CommunityNewsSidebar = component$<Props>((props) => {
  const { locale } = useI18n();
  const settings = useSiteSettings();
  const nav = useNavigate();
  const pending = usePendingState();
  const query = useSignal(props.initialQuery || "");
  const email = useSignal("");
  const submitting = useSignal(false);
  const turnstileToken = useSignal("");
  const turnstileResetKey = useSignal(0);

  const newsletterEnabled = Boolean(settings.value.newsletter?.enabled);
  const turnstile = settings.value.turnstile;
  const turnstileEnabled = Boolean(turnstile?.enabled && turnstile.site_key);

  return (
    <aside class="community-sidebar" aria-label={tStatic(locale, "community.sidebar")}>
      {newsletterEnabled ? (
        <section class="community-sidebar__card">
          <h2 class="community-sidebar__title">{tStatic(locale, "footer.newsletterTitle")}</h2>
          <p class="footer-muted">{tStatic(locale, "footer.newsletterBlurb")}</p>
          <form
            class="community-sidebar__newsletter"
            preventdefault:submit
            onSubmit$={async () => {
              const value = email.value.trim();
              if (!value || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                await toastError(tStatic(locale, "footer.newsletterInvalidEmail"));
                return;
              }
              if (turnstileEnabled && !turnstileToken.value) {
                await toastError(tStatic(locale, "turnstile.required"));
                return;
              }
              await withPendingFeedback(pending, submitting, async () => {
                try {
                  const { data } = await subscribeNewsletter({
                    email: value,
                    ...(turnstileEnabled ? { turnstile_token: turnstileToken.value } : {}),
                  });
                  await toastSuccess(data.message);
                  email.value = "";
                  turnstileResetKey.value += 1;
                } catch (err) {
                  await toastError(
                    err instanceof ApiError
                      ? err.message
                      : tStatic(locale, "footer.newsletterFailed"),
                  );
                  turnstileResetKey.value += 1;
                }
              });
            }}
          >
            <input
              type="email"
              name="email"
              autocomplete="email"
              required
              placeholder={tStatic(locale, "footer.newsletterPlaceholder")}
              value={email.value}
              onInput$={(e) => {
                email.value = (e.target as HTMLInputElement).value;
              }}
              disabled={submitting.value}
            />
            <button type="submit" class="btn btn-primary" disabled={submitting.value}>
              {submitting.value
                ? tStatic(locale, "footer.newsletterSubmitting")
                : tStatic(locale, "footer.newsletterSubscribe")}
            </button>
          </form>
          {turnstileEnabled && turnstile.site_key ? (
            <TurnstileWidget
              siteKey={turnstile.site_key}
              token={turnstileToken}
              resetKey={turnstileResetKey.value}
            />
          ) : null}
        </section>
      ) : null}

      <section class="community-sidebar__card">
        <h2 class="community-sidebar__title">{tStatic(locale, "community.searchTitle")}</h2>
        <form
          class="community-sidebar__search"
          preventdefault:submit
          onSubmit$={async () => {
            const q = query.value.trim();
            const path = q
              ? localePath(locale, `/gaming-news?q=${encodeURIComponent(q)}`)
              : localePath(locale, "/gaming-news");
            await nav(path);
          }}
        >
          <input
            type="search"
            name="q"
            value={query.value}
            placeholder={tStatic(locale, "community.searchPlaceholder")}
            onInput$={(e) => {
              query.value = (e.target as HTMLInputElement).value;
            }}
          />
          <button type="submit" class="btn btn-secondary">
            {tStatic(locale, "community.searchSubmit")}
          </button>
        </form>
        <p class="footer-muted community-sidebar__hint">
          {tStatic(locale, "community.searchHint")}
        </p>
      </section>

      <UpcomingList
        titleKey="community.upcomingTournaments"
        posts={props.upcomingTournaments}
        detailBase="/tournaments"
        emptyKey="community.noUpcoming"
      />
      <UpcomingList
        titleKey="community.upcomingEvents"
        posts={props.upcomingEvents}
        detailBase="/events"
        emptyKey="community.noUpcoming"
      />
    </aside>
  );
});

const UpcomingList = component$<{
  titleKey: string;
  posts: CommunityPostSummary[];
  detailBase: string;
  emptyKey: string;
}>((props) => {
  const { locale } = useI18n();

  return (
    <section class="community-sidebar__card">
      <h2 class="community-sidebar__title">{tStatic(locale, props.titleKey)}</h2>
      {props.posts.length === 0 ? (
        <p class="footer-muted">{tStatic(locale, props.emptyKey)}</p>
      ) : (
        <ul class="community-sidebar__list">
          {props.posts.slice(0, 5).map((post) => (
            <li key={post.id}>
              <Link href={localePath(locale, `${props.detailBase}/${post.slug}`)}>
                {post.title}
              </Link>
              {post.starts_at ? (
                <span class="community-sidebar__meta">
                  {formatCommunityWhen(post.starts_at, locale)}
                </span>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
});
