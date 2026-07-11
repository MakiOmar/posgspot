import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { ApiError, API_BASE, fetchDigitalGames } from "~/lib/api";
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
  const page = Math.max(1, Number(query.get("page") || 1) || 1);
  const storefrontRequestUrl = `${API_BASE}/api/storefront/v1/digital/games?platform=${platform}&page=${page}`;

  try {
    const { data } = await fetchDigitalGames(platform, page, locale);
    const games = (data.games ?? []) as DigitalGameSummary[];
    const debug = {
      ...((data as { debug?: GamesListDebug }).debug ?? {}),
      storefront_request_url: storefrontRequestUrl,
    } as GamesListDebug;

    return {
      enabled: true,
      platform,
      page,
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
      page,
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

  const debug = list.value.debug;
  const showDebug = list.value.games.length === 0;

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

      {showDebug && debug ? (
        <aside
          class="account-summary"
          style={{ marginTop: "1.5rem", fontFamily: "ui-monospace, monospace", fontSize: "0.8125rem" }}
        >
          <h2 style={{ fontSize: "1rem" }}>Digital catalog debug</h2>
          <p style={{ margin: "0.5rem 0", color: "var(--gs-accent)" }}>
            <strong>Reason:</strong> {debug.reason || "unknown"}
          </p>
          <dl style={{ display: "grid", gap: "0.35rem", margin: 0 }}>
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>Storefront request</dt>
              <dd style={{ margin: 0 }}>{debug.storefront_request_url}</dd>
            </div>
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>Accounts base</dt>
              <dd style={{ margin: 0 }}>{debug.accounts_base ?? "—"}</dd>
            </div>
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>Accounts request</dt>
              <dd style={{ margin: 0 }}>
                {debug.request_method || "GET"} {debug.request_url || debug.request_path || "—"}
              </dd>
            </div>
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>Platform / page</dt>
              <dd style={{ margin: 0 }}>
                PS{debug.platform ?? list.value.platform} · page {debug.page ?? list.value.page}
              </dd>
            </div>
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>Accounts HTTP</dt>
              <dd style={{ margin: 0 }}>
                {debug.http_status ?? "—"} · ok={String(debug.accounts_ok ?? false)}
              </dd>
            </div>
            {debug.error ? (
              <div>
                <dt style={{ color: "var(--gs-muted)" }}>Accounts error</dt>
                <dd style={{ margin: 0 }}>{debug.error}</dd>
              </div>
            ) : null}
            {debug.client_error ? (
              <div>
                <dt style={{ color: "var(--gs-muted)" }}>Client error</dt>
                <dd style={{ margin: 0 }}>{debug.client_error}</dd>
              </div>
            ) : null}
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>Response body keys</dt>
              <dd style={{ margin: 0 }}>{(debug.body_keys ?? []).join(", ") || "—"}</dd>
            </div>
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>Item counts</dt>
              <dd style={{ margin: 0 }}>
                raw={debug.raw_item_count ?? "—"} · normalized={debug.normalized_count ?? "—"} ·
                total={debug.paginator_total ?? list.value.meta.total ?? "—"}
              </dd>
            </div>
            <div>
              <dt style={{ color: "var(--gs-muted)" }}>POS digital variation IDs</dt>
              <dd style={{ margin: 0 }}>
                primary={debug.skus?.primary ?? list.value.skus.primary?.variation_id ?? "null"} ·
                secondary=
                {debug.skus?.secondary ?? list.value.skus.secondary?.variation_id ?? "null"}
              </dd>
            </div>
          </dl>
        </aside>
      ) : null}

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
