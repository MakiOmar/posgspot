import { $, component$, useSignal } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { checkDigitalGameStock, fetchDigitalGame } from "~/lib/api";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CartItemDigital, DigitalPosSku } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

type GameOffer = "primary" | "secondary";

export const useGameDetail = routeLoader$(async ({ params, query, redirect }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const id = Number(params.id);
  const platform = (query.get("platform") === "5" ? "5" : "4") as "4" | "5";

  if (!Number.isFinite(id) || id <= 0) {
    throw redirect(302, localePath(locale, "/games"));
  }

  try {
    const { data } = await fetchDigitalGame(id, locale);
    return { game: data.game as Record<string, unknown>, skus: data.skus, platform, ok: true as const };
  } catch {
    throw redirect(302, localePath(locale, "/games"));
  }
});

function num(value: unknown): number {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

function boolish(value: unknown): boolean {
  return value === true || value === 1 || value === "1";
}

export default component$(() => {
  const detail = useGameDetail();
  const settings = useSiteSettings();
  const cart = useCart();
  const nav = useNavigate();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const pending = useSignal<GameOffer | null>(null);

  const game = detail.value.game;
  const platform = detail.value.platform;
  const title = String(game.title ?? "");
  const image =
    platform === "5"
      ? String(game.ps5_image_url ?? game.image_url ?? "")
      : String(game.ps4_image_url ?? game.image_url ?? "");

  const primaryPrice = num(
    game[`ps${platform}_primary_price`] ?? game.primary_price ?? game.ps4_primary_price,
  );
  const secondaryPrice = num(
    game[`ps${platform}_secondary_price`] ?? game.secondary_price ?? game.ps4_secondary_price,
  );
  const primaryOk = boolish(game[`ps${platform}_primary_status`] ?? game.primary_status);
  const secondaryOk = boolish(game[`ps${platform}_secondary_status`] ?? game.secondary_status);
  const primaryStock = num(
    game[`ps${platform}_primary_stock`] ?? game.total_primary_stock ?? game.ps4_primary_stock,
  );
  const secondaryStock = num(
    game[`ps${platform}_secondary_stock`] ?? game.total_secondary_stock ?? game.ps4_secondary_stock,
  );

  const addOffer$ = $(async (offer: GameOffer) => {
    const sku: DigitalPosSku | null =
      offer === "primary" ? detail.value.skus.primary : detail.value.skus.secondary;
    if (!sku) {
      await toastError(tStatic(lang, "digital.skuMissing"));
      return;
    }
    const price = offer === "primary" ? primaryPrice : secondaryPrice;
    if (price <= 0) {
      await toastError(tStatic(lang, "digital.unavailable"));
      return;
    }

    pending.value = offer;
    try {
      await checkDigitalGameStock({
        game_id: Number(game.id),
        type: offer,
        platform,
      });
      const digital: CartItemDigital = {
        kind: "game",
        game_id: Number(game.id),
        type: offer,
        platform,
        line_key: `ps${platform}_${offer}_stock|game:${game.id}`,
        title: `${title} (${offer === "primary" ? "Primary" : "Secondary"} · PS${platform})`,
        price,
      };
      await addCartItem(cart, {
        productId: sku.product_id,
        variationId: sku.variation_id,
        slug: null,
        name: digital.title || title,
        variationName: offer === "primary" ? "Primary" : "Secondary",
        price,
        quantity: 1,
        imageUrl: image || sku.image_url,
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
                  {primaryStock > 0 ? (
                    <span class="footer-muted"> · {tStatic(lang, "digital.inStock")}</span>
                  ) : null}
                </p>
                <button
                  type="button"
                  class="btn btn-primary"
                  disabled={pending.value !== null}
                  onClick$={() => addOffer$("primary")}
                >
                  {pending.value === "primary"
                    ? tStatic(lang, "digital.adding")
                    : tStatic(lang, "digital.addPrimary")}
                </button>
              </div>
            ) : null}

            {secondaryOk && secondaryPrice > 0 ? (
              <div>
                <p>
                  <strong>{tStatic(lang, "digital.secondary")}</strong> —{" "}
                  {formatPrice(secondaryPrice, settings.value.currency, lang)}
                  {secondaryStock > 0 ? (
                    <span class="footer-muted"> · {tStatic(lang, "digital.inStock")}</span>
                  ) : null}
                </p>
                <button
                  type="button"
                  class="btn btn-secondary"
                  disabled={pending.value !== null}
                  onClick$={() => addOffer$("secondary")}
                >
                  {pending.value === "secondary"
                    ? tStatic(lang, "digital.adding")
                    : tStatic(lang, "digital.addSecondary")}
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
  const title = String(detail.game.title ?? tStatic(lang, "nav.games"));
  return withStorefrontThemeHead(
    {
      title: `${title} — ${settings.business_name}`,
      meta: [{ name: "description", content: tStatic(lang, "digital.gameDetailDescription", { title }) }],
      links: publicSeoLinks(url.origin, `/games/${detail.game.id}`, lang),
    },
    settings,
  );
};
