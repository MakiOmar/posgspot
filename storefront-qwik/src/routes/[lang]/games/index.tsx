import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { fetchDigitalGames } from "~/lib/api";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { DigitalGameSummary } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useGamesList = routeLoader$(async ({ query, params, redirect }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const platform = query.get("platform") === "5" ? "5" : "4";
  const page = Math.max(1, Number(query.get("page") || 1) || 1);

  try {
    const { data } = await fetchDigitalGames(platform, page, locale);
    return {
      enabled: true,
      platform,
      games: (data.games ?? []) as DigitalGameSummary[],
      meta: data.meta,
      skus: data.skus,
    };
  } catch (e: unknown) {
    const status = typeof e === "object" && e && "status" in e ? Number((e as { status: number }).status) : 0;
    if (status === 503) {
      throw redirect(302, localePath(locale, "/products"));
    }
    return {
      enabled: false,
      platform,
      games: [] as DigitalGameSummary[],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      skus: { primary: null, secondary: null, gift_card: null },
    };
  }
});

export default component$(() => {
  const list = useGamesList();
  const settings = useSiteSettings();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const listPath = loc.url.pathname || localePath(lang, "/games");

  const buildUrl = (platform: string, page = 1) => {
    const params = new URLSearchParams();
    if (platform !== "4") {
      params.set("platform", platform);
    }
    if (page > 1) {
      params.set("page", String(page));
    }
    const qs = params.toString();
    return qs ? `${listPath}?${qs}` : listPath;
  };

  return (
    <section>
      <nav class="content-breadcrumb" aria-label={tStatic(lang, "a11y.breadcrumb")}>
        <Link href={localePath(lang, "/")}>{tStatic(lang, "nav.home")}</Link>
        <span aria-hidden="true"> / </span>
        <span>{tStatic(lang, "nav.games")}</span>
      </nav>

      <h1 class="page-title">{tStatic(lang, "digital.gamesTitle")}</h1>
      <p class="footer-muted">{tStatic(lang, "digital.gamesLead")}</p>

      <div class="product-list-toolbar" style={{ marginBottom: "1.25rem" }}>
        <Link
          href={buildUrl("4")}
          class={list.value.platform === "4" ? "btn btn-primary" : "btn btn-secondary"}
        >
          PS4
        </Link>
        <Link
          href={buildUrl("5")}
          class={list.value.platform === "5" ? "btn btn-primary" : "btn btn-secondary"}
          style={{ marginInlineStart: "0.5rem" }}
        >
          PS5
        </Link>
      </div>

      {list.value.games.length === 0 ? (
        <div class="empty-state">{tStatic(lang, "digital.noGames")}</div>
      ) : (
        <div class="product-grid">
          {list.value.games.map((game) => {
            const price = Number(game.primary_price ?? game.secondary_price ?? 0);
            return (
              <Link
                key={game.id}
                href={localePath(lang, `/games/${game.id}?platform=${list.value.platform}`)}
                class="product-card"
              >
                <div class="product-card__media">
                  {game.image_url ? (
                    <img src={game.image_url} alt="" width={320} height={320} loading="lazy" />
                  ) : (
                    <span class="product-card__placeholder" aria-hidden="true" />
                  )}
                </div>
                <div class="product-card__body">
                  <h2 class="product-card__title">{game.title}</h2>
                  {price > 0 ? (
                    <p class="product-card__price">
                      {formatPrice(price, settings.value.currency, lang)}
                    </p>
                  ) : null}
                </div>
              </Link>
            );
          })}
        </div>
      )}

      {list.value.meta.last_page > 1 ? (
        <nav class="pagination" aria-label={tStatic(lang, "a11y.pagination")}>
          {list.value.meta.current_page > 1 ? (
            <Link href={buildUrl(list.value.platform, list.value.meta.current_page - 1)}>
              {tStatic(lang, "common.prev")}
            </Link>
          ) : null}
          <span class="active">
            {tStatic(lang, "common.pageOf", {
              current: list.value.meta.current_page,
              last: list.value.meta.last_page,
            })}
          </span>
          {list.value.meta.current_page < list.value.meta.last_page ? (
            <Link href={buildUrl(list.value.platform, list.value.meta.current_page + 1)}>
              {tStatic(lang, "common.next")}
            </Link>
          ) : null}
        </nav>
      ) : null}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const lang = resolveValue(useLangParam);
  const settings = resolveValue(useSiteSettings);
  return withStorefrontThemeHead(
    {
      title: tStatic(lang, "digital.gamesSeoTitle", { businessName: settings.business_name }),
      meta: [
        {
          name: "description",
          content: tStatic(lang, "digital.gamesSeoDescription", {
            businessName: settings.business_name,
          }),
        },
      ],
      links: publicSeoLinks(url.origin, "/games", lang),
    },
    settings,
  );
};
