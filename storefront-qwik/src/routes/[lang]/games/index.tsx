import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { ApiError, API_BASE, fetchDigitalGames } from "~/lib/api";
import { digitalListGameInStock } from "~/lib/digital-game";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { DigitalGameSummary, DigitalSkus } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

interface GamesListDebug {
  accounts_base?: string;
  request_method?: string;
  request_path?: string;
  request_url?: string;
  platform?: string;
  page?: number;
  http_status?: number;
  accounts_ok?: boolean;
  error?: string | null;
  body_keys?: string[];
  raw_item_count?: number;
  normalized_count?: number;
  paginator_total?: number | null;
  skus?: { primary?: number | null; secondary?: number | null };
  reason?: string;
  storefront_request_url?: string;
  client_error?: string;
}

export const useGamesList = routeLoader$(async ({ query, params, redirect }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const platform = (query.get("platform") === "5" ? "5" : "4") as "4" | "5";
  const productType =
    query.get("product_type") === "subscription" ? "subscription" : "game";
  const page = Math.max(1, Number(query.get("page") || 1) || 1);
  const q = (query.get("q") || "").trim();
  const storefrontRequestUrl = `${API_BASE}/api/storefront/v1/digital/games?platform=${platform}&product_type=${productType}&page=${page}${q ? `&q=${encodeURIComponent(q)}` : ""}`;

  try {
    const { data } = await fetchDigitalGames(
      platform,
      page,
      locale,
      q || undefined,
      productType,
    );
    const games = (data.games ?? []) as DigitalGameSummary[];
    const debug = {
      ...((data as { debug?: GamesListDebug }).debug ?? {}),
      storefront_request_url: storefrontRequestUrl,
    } as GamesListDebug;

    return {
      enabled: true,
      platform,
      productType,
      page,
      q,
      games,
      meta: data.meta,
      skus: data.skus as DigitalSkus,
      debug,
    };
  } catch (e: unknown) {
    const status = e instanceof ApiError ? e.status : 0;
    if (status === 503) {
      throw redirect(302, localePath(locale, "/products"));
    }
    const message = e instanceof Error ? e.message : "Unknown client error";
    return {
      enabled: true,
      platform,
      productType,
      page,
      q,
      games: [] as DigitalGameSummary[],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      skus: { primary: null, secondary: null, gift_card: null } as DigitalSkus,
      debug: {
        storefront_request_url: storefrontRequestUrl,
        platform,
        page,
        http_status: status,
        accounts_ok: false,
        client_error: message,
        reason: `Storefront API request failed (HTTP ${status || "?"}): ${message}`,
      } as GamesListDebug,
    };
  }
});

export default component$(() => {
  const list = useGamesList();
  const settings = useSiteSettings();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const listPath = loc.url.pathname || localePath(lang, "/games");
  const isPlus = list.value.productType === "subscription";

  const buildUrl = (platform: string, page = 1) => {
    const params = new URLSearchParams();
    if (platform !== "4") {
      params.set("platform", platform);
    }
    if (isPlus) {
      params.set("product_type", "subscription");
    }
    if (list.value.q) {
      params.set("q", list.value.q);
    }
    if (page > 1) {
      params.set("page", String(page));
    }
    const qs = params.toString();
    return qs ? `${listPath}?${qs}` : listPath;
  };

  const debug = list.value.debug;
  const showDebug = list.value.games.length === 0;

  return (
    <section class="digital-catalog">
      <PageTitleBar
        title={
          isPlus
            ? tStatic(lang, "digital.psPlusTitle")
            : tStatic(lang, "digital.gamesTitle")
        }
        crumbs={[
          {
            label: isPlus
              ? tStatic(lang, "nav.gamesPsPlus")
              : tStatic(lang, "nav.games"),
          },
        ]}
      />

      <header class="digital-catalog__header">
        <p class="footer-muted digital-catalog__lead">
          {isPlus
            ? tStatic(lang, "digital.psPlusLead")
            : tStatic(lang, "digital.gamesLead")}
        </p>

        <div class="digital-catalog__platforms" role="tablist" aria-label={tStatic(lang, "digital.platformFilter")}>
          <Link
            href={buildUrl("4")}
            role="tab"
            aria-selected={list.value.platform === "4"}
            class={`digital-catalog__platform${list.value.platform === "4" ? " is-active" : ""}`}
          >
            PS4
          </Link>
          <Link
            href={buildUrl("5")}
            role="tab"
            aria-selected={list.value.platform === "5"}
            class={`digital-catalog__platform${list.value.platform === "5" ? " is-active" : ""}`}
          >
            PS5
          </Link>
        </div>

        {list.value.games.length > 0 ? (
          <p class="footer-muted digital-catalog__count">
            {tStatic(lang, "digital.gamesCount", {
              count: String(list.value.meta.total ?? list.value.games.length),
              platform: `PS${list.value.platform}`,
            })}
          </p>
        ) : null}
      </header>

      {list.value.games.length === 0 ? (
        <div class="empty-state">
          {isPlus ? tStatic(lang, "digital.noPsPlus") : tStatic(lang, "digital.noGames")}
        </div>
      ) : (
        <div class="product-grid digital-catalog__grid">
          {list.value.games.map((game) => {
            const price = Number(
              game.primary_price ?? game.secondary_price ?? game.full_price ?? 0,
            );
            const inStock = digitalListGameInStock(game);
            return (
              <Link
                key={game.id}
                href={localePath(
                  lang,
                  `/games/${game.id}?platform=${list.value.platform}`,
                )}
                class="product-card digital-game-card"
                prefetch={false}
              >
                <div class="product-card__media digital-game-card__media">
                  {game.image_url ? (
                    <img
                      class="product-card__image digital-game-card__image"
                      src={game.image_url}
                      alt={game.title}
                      width={320}
                      height={320}
                      loading="lazy"
                    />
                  ) : (
                    <div class="product-card__image digital-game-card__image" aria-hidden="true" />
                  )}
                </div>
                <div class="product-card__body digital-game-card__body">
                  <h2 class="product-card__name digital-game-card__title">{game.title}</h2>
                  {price > 0 ? (
                    <p class="product-card__price digital-game-card__price">
                      {formatPrice(price, settings.value.currency, lang)}
                    </p>
                  ) : (
                    <p class="footer-muted digital-game-card__price">
                      {tStatic(lang, "digital.unavailable")}
                    </p>
                  )}
                  <p class="footer-muted">
                    {inStock ? tStatic(lang, "digital.inStock") : tStatic(lang, "digital.outOfStock")}
                  </p>
                </div>
              </Link>
            );
          })}
        </div>
      )}

      {showDebug && debug ? (
        <aside class="digital-catalog__debug" aria-label="Digital catalog debug">
          <h2>Digital catalog debug</h2>
          <p class="digital-catalog__debug-reason">
            <strong>Reason:</strong> {debug.reason || "unknown"}
          </p>
          <dl>
            <div>
              <dt>Storefront request</dt>
              <dd>{debug.storefront_request_url}</dd>
            </div>
            <div>
              <dt>Accounts base</dt>
              <dd>{debug.accounts_base ?? "—"}</dd>
            </div>
            <div>
              <dt>Accounts request</dt>
              <dd>
                {debug.request_method || "GET"} {debug.request_url || debug.request_path || "—"}
              </dd>
            </div>
            <div>
              <dt>Platform / page</dt>
              <dd>
                PS{debug.platform ?? list.value.platform} · page {debug.page ?? list.value.page}
              </dd>
            </div>
            <div>
              <dt>Accounts HTTP</dt>
              <dd>
                {debug.http_status ?? "—"} · ok={String(debug.accounts_ok ?? false)}
              </dd>
            </div>
            {debug.error ? (
              <div>
                <dt>Accounts error</dt>
                <dd>{debug.error}</dd>
              </div>
            ) : null}
            {debug.client_error ? (
              <div>
                <dt>Client error</dt>
                <dd>{debug.client_error}</dd>
              </div>
            ) : null}
            <div>
              <dt>Response body keys</dt>
              <dd>{(debug.body_keys ?? []).join(", ") || "—"}</dd>
            </div>
            <div>
              <dt>Item counts</dt>
              <dd>
                raw={debug.raw_item_count ?? "—"} · normalized={debug.normalized_count ?? "—"} ·
                total={debug.paginator_total ?? list.value.meta.total ?? "—"}
              </dd>
            </div>
            <div>
              <dt>POS digital variation IDs</dt>
              <dd>
                primary={debug.skus?.primary ?? list.value.skus.primary?.variation_id ?? "null"} ·
                secondary=
                {debug.skus?.secondary ?? list.value.skus.secondary?.variation_id ?? "null"}
              </dd>
            </div>
          </dl>
        </aside>
      ) : null}

      {list.value.meta.last_page > 1 ? (
        <nav class="pagination digital-catalog__pagination" aria-label={tStatic(lang, "a11y.pagination")}>
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
  const list = resolveValue(useGamesList);
  const isPlus = list.productType === "subscription";
  return withStorefrontThemeHead(
    {
      title: tStatic(
        lang,
        isPlus ? "digital.psPlusSeoTitle" : "digital.gamesSeoTitle",
        { businessName: settings.business_name },
      ),
      meta: [
        {
          name: "description",
          content: tStatic(
            lang,
            isPlus ? "digital.psPlusSeoDescription" : "digital.gamesSeoDescription",
            { businessName: settings.business_name },
          ),
        },
      ],
      links: publicSeoLinks(url.origin, "/games", lang),
    },
    settings,
  );
};
