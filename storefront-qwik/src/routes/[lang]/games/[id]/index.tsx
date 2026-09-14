import { $, component$, useSignal } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { ApiError, fetchDigitalGame, checkDigitalGameStock } from "~/lib/api";
import {
  digitalOfferEnabled,
  digitalOfferInStock,
  digitalOfferPrice,
  digitalOfferStock,
  liveCheckStockIsOut,
  type DigitalPlatform,
} from "~/lib/digital-game";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CartItemDigital, DigitalPosSku, DigitalSkus } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

type GameOffer = "primary" | "secondary";

const emptySkus: DigitalSkus = { primary: null, secondary: null, gift_card: null };

export const useGameDetail = routeLoader$(async ({ params, query }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const id = Number(params.id);
  const platform = (query.get("platform") === "5" ? "5" : "4") as DigitalPlatform;

  if (!Number.isFinite(id) || id <= 0) {
    return { ok: false as const, notFound: true, error: "invalid", platform, game: null, skus: emptySkus };
  }

  try {
    const { data } = await fetchDigitalGame(id, locale);
    return { ok: true as const, game: data.game as Record<string, unknown>, skus: data.skus, platform };
  } catch (e) {
    const status = e instanceof ApiError ? e.status : 0;
    const message = e instanceof Error ? e.message : "Failed to load game";
    return {
      ok: false as const,
      notFound: status === 404,
      error: message,
      platform,
      game: null,
      skus: emptySkus,
    };
  }
});

export default component$(() => {
  const detail = useGameDetail();
  const settings = useSiteSettings();
  const cart = useCart();
  const nav = useNavigate();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const pending = useSignal<GameOffer | null>(null);

  if (!detail.value.ok || !detail.value.game) {
    return (
      <article>
        {/* Stay on this URL so a timeout is not mistaken for "the game list never left". */}
        <nav class="content-breadcrumb" aria-label={tStatic(lang, "a11y.breadcrumb")}>
          <Link href={localePath(lang, "/")}>{tStatic(lang, "nav.home")}</Link>
          <span aria-hidden="true"> / </span>
          <Link href={localePath(lang, "/games")}>{tStatic(lang, "nav.games")}</Link>
        </nav>
        <div class="empty-state" style={{ marginTop: "2rem" }}>
          <p>
            {detail.value.notFound
              ? tStatic(lang, "digital.gameNotFound")
              : tStatic(lang, "digital.gameLoadFailed")}
          </p>
          <p>
            <Link href={localePath(lang, "/games")} class="btn btn-secondary">
              {tStatic(lang, "nav.games")}
            </Link>{" "}
            {!detail.value.notFound ? (
              <button
                type="button"
                class="btn btn-primary"
                onClick$={() => nav(loc.url.pathname + loc.url.search)}
              >
                {tStatic(lang, "digital.retry")}
              </button>
            ) : null}
          </p>
        </div>
      </article>
    );
  }

  const game = detail.value.game;
  const platform = detail.value.platform;
  const title = String(game.title ?? "");
  const image =
    platform === "5"
      ? String(game.ps5_image_url ?? game.image_url ?? "")
      : String(game.ps4_image_url ?? game.image_url ?? "");

  const primaryPrice = digitalOfferPrice(game, platform, "primary");
  const secondaryPrice = digitalOfferPrice(game, platform, "secondary");
  const primaryOk = digitalOfferEnabled(game, platform, "primary");
  const secondaryOk = digitalOfferEnabled(game, platform, "secondary");
  const primaryInStock = digitalOfferInStock(game, platform, "primary");
  const secondaryInStock = digitalOfferInStock(game, platform, "secondary");

  const addOffer$ = $(async (offer: GameOffer) => {
    const gameData = detail.value.game;
    if (!gameData) {
      return;
    }
    const plat = detail.value.platform;
    const sku: DigitalPosSku | null =
      offer === "primary" ? detail.value.skus.primary : detail.value.skus.secondary;
    if (!sku) {
      await toastError(tStatic(lang, "digital.skuMissing"));
      return;
    }
    const price = digitalOfferPrice(gameData, plat, offer);
    const stock = digitalOfferStock(gameData, plat, offer);
    const offerEnabled = digitalOfferEnabled(gameData, plat, offer);
    if (!offerEnabled || price <= 0) {
      await toastError(tStatic(lang, "digital.unavailable"));
      return;
    }
    if (stock <= 0) {
      await toastError(tStatic(lang, "digital.outOfStock"));
      return;
    }

    const gameTitle = String(gameData.title ?? title);
    const gameImage =
      plat === "5"
        ? String(gameData.ps5_image_url ?? gameData.image_url ?? image)
        : String(gameData.ps4_image_url ?? gameData.image_url ?? image);

    pending.value = offer;
    try {
      const stockCheck = await checkDigitalGameStock({
        game_id: Number(gameData.id),
        type: offer,
        platform: plat,
      });
      const stockData = stockCheck.data as { is_available?: boolean; stock?: number | string };
      if (liveCheckStockIsOut(stockData)) {
        await toastError(tStatic(lang, "digital.outOfStock"));
        return;
      }
      const digital: CartItemDigital = {
        kind: "game",
        game_id: Number(gameData.id),
        type: offer,
        platform: plat,
        line_key: `ps${plat}_${offer}_stock|game:${gameData.id}`,
        title: `${gameTitle} (${offer === "primary" ? "Primary" : "Secondary"} · PS${plat})`,
        price,
      };
      await addCartItem(cart, {
        productId: sku.product_id,
        variationId: sku.variation_id,
        slug: null,
        name: digital.title || gameTitle,
        variationName: offer === "primary" ? "Primary" : "Secondary",
        price,
        quantity: 1,
        imageUrl: gameImage || sku.image_url,
        digital,
      });
      await toastSuccess(tStatic(lang, "digital.addedToCart"));
      await nav(localePath(lang, "/cart"));
    } catch (e) {
      await toastError(
        e instanceof Error ? e.message : tStatic(lang, "digital.stockFailed"),
      );
    } finally {
      pending.value = null;
    }
  });

  return (
    <article>
      <nav class="content-breadcrumb" aria-label={tStatic(lang, "a11y.breadcrumb")}>
        <Link href={localePath(lang, "/")}>{tStatic(lang, "nav.home")}</Link>
        <span aria-hidden="true"> / </span>
        <Link href={localePath(lang, "/games")}>{tStatic(lang, "nav.games")}</Link>
        <span aria-hidden="true"> / </span>
        <span>{title}</span>
      </nav>

      <div class="pdp-layout" style={{ marginTop: "1rem" }}>
        <div class="pdp-gallery">
          {image ? (
            <img src={image} alt={title} width={640} height={640} />
          ) : (
            <div class="product-card__placeholder" aria-hidden="true" />
          )}
        </div>
        <div class="pdp-summary">
          <h1 class="page-title" style={{ marginTop: 0 }}>
            {title}
          </h1>
          <p class="footer-muted">{tStatic(lang, "digital.platformLabel", { platform })}</p>

          <div style={{ display: "grid", gap: "1rem", marginTop: "1.5rem" }}>
            {primaryOk && primaryPrice > 0 ? (
              <div>
                <p>
                  <strong>{tStatic(lang, "digital.primary")}</strong> —{" "}
                  {formatPrice(primaryPrice, settings.value.currency, lang)}
                  <span class="footer-muted">
                    {" "}
                    ·{" "}
                    {primaryInStock
                      ? tStatic(lang, "digital.inStock")
                      : tStatic(lang, "digital.outOfStock")}
                  </span>
                </p>
                <button
                  type="button"
                  class="btn btn-primary"
                  disabled={!primaryInStock || pending.value !== null}
                  onClick$={() => addOffer$("primary")}
                >
                  {pending.value === "primary"
                    ? tStatic(lang, "digital.adding")
                    : primaryInStock
                      ? tStatic(lang, "digital.addPrimary")
                      : tStatic(lang, "digital.outOfStock")}
                </button>
              </div>
            ) : null}

            {secondaryOk && secondaryPrice > 0 ? (
              <div>
                <p>
                  <strong>{tStatic(lang, "digital.secondary")}</strong> —{" "}
                  {formatPrice(secondaryPrice, settings.value.currency, lang)}
                  <span class="footer-muted">
                    {" "}
                    ·{" "}
                    {secondaryInStock
                      ? tStatic(lang, "digital.inStock")
                      : tStatic(lang, "digital.outOfStock")}
                  </span>
                </p>
                <button
                  type="button"
                  class="btn btn-secondary"
                  disabled={!secondaryInStock || pending.value !== null}
                  onClick$={() => addOffer$("secondary")}
                >
                  {pending.value === "secondary"
                    ? tStatic(lang, "digital.adding")
                    : secondaryInStock
                      ? tStatic(lang, "digital.addSecondary")
                      : tStatic(lang, "digital.outOfStock")}
                </button>
              </div>
            ) : null}

            {!primaryOk && !secondaryOk ? (
              <p class="footer-muted">{tStatic(lang, "digital.unavailable")}</p>
            ) : null}
          </div>
        </div>
      </div>
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const lang = resolveValue(useLangParam);
  const settings = resolveValue(useSiteSettings);
  const detail = resolveValue(useGameDetail);

  if (!detail.ok || !detail.game) {
    return withStorefrontThemeHead(
      {
        title: `${tStatic(lang, "digital.gamesTitle")} — ${settings.business_name}`,
        meta: [
          {
            name: "description",
            content: detail.notFound
              ? tStatic(lang, "digital.gameNotFound")
              : tStatic(lang, "digital.gameLoadFailed"),
          },
          { name: "robots", content: "noindex, nofollow" },
        ],
      },
      settings,
    );
  }

  const title = String(detail.game.title ?? tStatic(lang, "nav.games"));
  const description = tStatic(lang, "digital.gameDetailDescription", { title });
  const image =
    detail.platform === "5"
      ? String(detail.game.ps5_image_url ?? detail.game.image_url ?? "")
      : String(detail.game.ps4_image_url ?? detail.game.image_url ?? "");
  const canonicalPath = `/games/${detail.game.id}`;
  const pageUrl = `${url.origin.replace(/\/$/, "")}${localePath(lang, canonicalPath)}`;

  return withStorefrontThemeHead(
    {
      title: `${title} — ${settings.business_name}`,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: `${title} — ${settings.business_name}` },
        { property: "og:description", content: description },
        { property: "og:type", content: "product" },
        { property: "og:url", content: pageUrl },
        ...(image ? [{ property: "og:image", content: image }] : []),
        { name: "twitter:card", content: image ? "summary_large_image" : "summary" },
        { name: "twitter:title", content: `${title} — ${settings.business_name}` },
        { name: "twitter:description", content: description },
        ...(image ? [{ name: "twitter:image", content: image }] : []),
      ],
      links: publicSeoLinks(url.origin, canonicalPath, lang),
    },
    settings,
  );
};
