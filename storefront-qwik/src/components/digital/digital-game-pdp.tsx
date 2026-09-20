import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import { ProductShareButtons } from "~/components/catalog/product-share-buttons";
import { FaqAccordion } from "~/components/content/content-blocks";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { ImageLightbox } from "~/components/ui/image-lightbox";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { ApiError, checkDigitalGameStock } from "~/lib/api";
import {
  digitalOfferEnabled,
  digitalOfferInStock,
  digitalOfferPrice,
  digitalOfferStock,
  liveCheckStockIsOut,
  type DigitalPlatform,
} from "~/lib/digital-game";
import { formatPrice } from "~/lib/format";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import type {
  CartItemDigital,
  DigitalGameSummary,
  DigitalPosSku,
  DigitalSkus,
  StoreSettings,
} from "~/lib/types";
import { buildWhatsAppUrl } from "~/lib/whatsapp";

type GameOffer = "primary" | "secondary";

export interface DigitalPdpFaq {
  question: string;
  answer: string;
}

interface DigitalGamePdpProps {
  game: Record<string, unknown>;
  skus: DigitalSkus;
  platform: DigitalPlatform;
  alsoBought: DigitalGameSummary[];
  faqs: DigitalPdpFaq[];
  askWhatsApp: string | null;
  currency: StoreSettings["currency"];
}

/**
 * Sigma-style digital game PDP: buy box, trust, optional about, also-bought, FAQ.
 * Route-local — do not import from layouts.
 */
export const DigitalGamePdp = component$<DigitalGamePdpProps>((props) => {
  const cart = useCart();
  const nav = useNavigate();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const selected = useSignal<GameOffer>(
    digitalOfferEnabled(props.game, props.platform, "primary") &&
      digitalOfferPrice(props.game, props.platform, "primary") > 0
      ? "primary"
      : "secondary",
  );
  const pending = useSignal(false);
  const livePrimaryOut = useSignal(false);
  const liveSecondaryOut = useSignal(false);
  const lightboxIndex = useSignal<number | null>(null);
  const faqOpen = useSignal<number | null>(null);

  // Confirm Accounts stock after paint so an OOS offer cannot stay clickable.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    const gameData = props.game;
    const plat = props.platform;
    const confirm = async (offer: GameOffer): Promise<boolean> => {
      if (!digitalOfferInStock(gameData, plat, offer)) {
        return true;
      }
      try {
        const stockCheck = await checkDigitalGameStock({
          game_id: Number(gameData.id),
          type: offer,
          platform: plat,
        });
        return liveCheckStockIsOut(
          stockCheck.data as { is_available?: boolean; stock?: number | string },
        );
      } catch (e) {
        return e instanceof ApiError && e.status === 422;
      }
    };
    livePrimaryOut.value = await confirm("primary");
    liveSecondaryOut.value = await confirm("secondary");
  });

  const game = props.game;
  const platform = props.platform;
  const title = String(game.title ?? "");
  const image =
    platform === "5"
      ? String(game.ps5_image_url ?? game.image_url ?? "")
      : String(game.ps4_image_url ?? game.image_url ?? "");
  const description =
    typeof game.description === "string" && game.description.trim() !== ""
      ? game.description
      : null;

  const primaryOk = digitalOfferEnabled(game, platform, "primary");
  const secondaryOk = digitalOfferEnabled(game, platform, "secondary");
  const primaryPrice = digitalOfferPrice(game, platform, "primary");
  const secondaryPrice = digitalOfferPrice(game, platform, "secondary");
  const primaryInStock =
    digitalOfferInStock(game, platform, "primary") && !livePrimaryOut.value;
  const secondaryInStock =
    digitalOfferInStock(game, platform, "secondary") && !liveSecondaryOut.value;

  // Prefer an in-stock offer as the default selection when Primary is unavailable.
  const activeOffer: GameOffer =
    selected.value === "primary" && (!primaryOk || primaryPrice <= 0) && secondaryOk
      ? "secondary"
      : selected.value === "secondary" && (!secondaryOk || secondaryPrice <= 0) && primaryOk
        ? "primary"
        : selected.value;
  const activeInStock = activeOffer === "primary" ? primaryInStock : secondaryInStock;
  const activePrice = activeOffer === "primary" ? primaryPrice : secondaryPrice;
  const activeOk = activeOffer === "primary" ? primaryOk : secondaryOk;

  const origin = loc.url.origin;
  const pagePath = localePath(lang, `/games/${String(game.id)}`);
  const pageUrl = `${origin}${pagePath}`;
  const offerLabel =
    activeOffer === "primary"
      ? tStatic(lang, "digital.primary")
      : tStatic(lang, "digital.secondary");
  const waMessage = tStatic(lang, "digital.askWhatsAppMessage", {
    title,
    platform: String(platform),
    offer: offerLabel,
    url: pageUrl,
  });
  const waHref = buildWhatsAppUrl(props.askWhatsApp, waMessage);

  const addSelected$ = $(async () => {
    const gameData = props.game;
    const plat = props.platform;
    let offer: GameOffer = selected.value;
    const primaryAvailable =
      digitalOfferEnabled(gameData, plat, "primary") &&
      digitalOfferPrice(gameData, plat, "primary") > 0;
    const secondaryAvailable =
      digitalOfferEnabled(gameData, plat, "secondary") &&
      digitalOfferPrice(gameData, plat, "secondary") > 0;
    if (offer === "primary" && !primaryAvailable && secondaryAvailable) {
      offer = "secondary";
    } else if (offer === "secondary" && !secondaryAvailable && primaryAvailable) {
      offer = "primary";
    }
    const sku: DigitalPosSku | null =
      offer === "primary" ? props.skus.primary : props.skus.secondary;
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
      if (offer === "primary") {
        livePrimaryOut.value = true;
      } else {
        liveSecondaryOut.value = true;
      }
      await toastError(tStatic(lang, "digital.outOfStock"));
      return;
    }

    const gameTitle = String(gameData.title ?? title);
    const gameImage =
      plat === "5"
        ? String(gameData.ps5_image_url ?? gameData.image_url ?? image)
        : String(gameData.ps4_image_url ?? gameData.image_url ?? image);

    pending.value = true;
    try {
      const stockCheck = await checkDigitalGameStock({
        game_id: Number(gameData.id),
        type: offer,
        platform: plat,
      });
      const stockData = stockCheck.data as {
        is_available?: boolean;
        stock?: number | string;
      };
      if (liveCheckStockIsOut(stockData)) {
        if (offer === "primary") {
          livePrimaryOut.value = true;
        } else {
          liveSecondaryOut.value = true;
        }
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
      if (e instanceof ApiError && e.status === 422) {
        if (offer === "primary") {
          livePrimaryOut.value = true;
        } else {
          liveSecondaryOut.value = true;
        }
      }
      await toastError(
        e instanceof Error ? e.message : tStatic(lang, "digital.stockFailed"),
      );
    } finally {
      pending.value = false;
    }
  });

  const toggleFaq$ = $((index: number) => {
    faqOpen.value = faqOpen.value === index ? null : index;
  });

  return (
    <article class="digital-pdp-page">
      <PageTitleBar
        title={title}
        crumbs={[
          { label: tStatic(lang, "nav.games"), href: "/games" },
          { label: title },
        ]}
      />

      <div class="pdp-layout digital-pdp" style={{ marginTop: "1rem" }}>
        <div class="pdp-gallery pdp-gallery--digital">
          {image ? (
            <>
              <div class="pdp-gallery__main">
                <button
                  type="button"
                  class="pdp-gallery__main-btn"
                  aria-label={tStatic(lang, "a11y.lightboxOpen")}
                  onClick$={() => {
                    lightboxIndex.value = 0;
                  }}
                >
                  <img
                    src={image}
                    alt={title}
                    width={480}
                    height={480}
                    loading="eager"
                    decoding="async"
                  />
                </button>
              </div>
              <ImageLightbox images={[{ src: image, alt: title }]} index={lightboxIndex} />
            </>
          ) : (
            <div class="pdp-gallery__main">
              <div class="pdp-gallery__placeholder" aria-hidden="true" />
            </div>
          )}
        </div>

        <div class="pdp-summary digital-pdp__info">
          <span
            class={`stock-pill ${activeInStock && activeOk ? "stock-pill--in" : "stock-pill--out"}`}
          >
            {activeInStock && activeOk
              ? tStatic(lang, "digital.inStock")
              : tStatic(lang, "digital.outOfStock")}
          </span>

          <h1 class="digital-pdp__title">{title}</h1>
          <p class="footer-muted">{tStatic(lang, "digital.platformLabel", { platform })}</p>

          {activeOk && activePrice > 0 ? (
            <p class="pdp-price digital-pdp__price">
              {formatPrice(activePrice, props.currency, lang)}
            </p>
          ) : null}

          <div class="digital-pdp__offers" role="list">
            {primaryOk && primaryPrice > 0 ? (
              <button
                type="button"
                role="listitem"
                class={`digital-pdp__offer${activeOffer === "primary" ? " digital-pdp__offer--selected" : ""}`}
                onClick$={() => {
                  selected.value = "primary";
                }}
              >
                <span class="digital-pdp__offer-label">{tStatic(lang, "digital.primary")}</span>
                <span class="digital-pdp__offer-price">
                  {formatPrice(primaryPrice, props.currency, lang)}
                </span>
                <span class="footer-muted">
                  {primaryInStock
                    ? tStatic(lang, "digital.inStock")
                    : tStatic(lang, "digital.outOfStock")}
                </span>
              </button>
            ) : null}
            {secondaryOk && secondaryPrice > 0 ? (
              <button
                type="button"
                role="listitem"
                class={`digital-pdp__offer${activeOffer === "secondary" ? " digital-pdp__offer--selected" : ""}`}
                onClick$={() => {
                  selected.value = "secondary";
                }}
              >
                <span class="digital-pdp__offer-label">{tStatic(lang, "digital.secondary")}</span>
                <span class="digital-pdp__offer-price">
                  {formatPrice(secondaryPrice, props.currency, lang)}
                </span>
                <span class="footer-muted">
                  {secondaryInStock
                    ? tStatic(lang, "digital.inStock")
                    : tStatic(lang, "digital.outOfStock")}
                </span>
              </button>
            ) : null}
          </div>

          {!primaryOk && !secondaryOk ? (
            <p class="footer-muted">{tStatic(lang, "digital.unavailable")}</p>
          ) : null}

          <aside class="digital-pdp__notice" role="note">
            <strong>{tStatic(lang, "digital.importantNoticeTitle")}</strong>
            <p>{tStatic(lang, "digital.importantNoticeBody")}</p>
          </aside>

          <div class="pdp-actions">
            <button
              type="button"
              class="btn btn-primary"
              disabled={!activeOk || !activeInStock || pending.value}
              onClick$={addSelected$}
            >
              {pending.value
                ? tStatic(lang, "digital.adding")
                : tStatic(lang, "catalog.addToCart")}
            </button>
          </div>

          {waHref ? (
            <a
              class="btn btn-secondary digital-pdp__ask-wa"
              href={waHref}
              target="_blank"
              rel="noopener noreferrer"
            >
              {tStatic(lang, "digital.askWhatsApp")}
            </a>
          ) : null}

          <ProductShareButtons title={title} url={pageUrl} />

          <ul class="digital-pdp__trust">
            <li>{tStatic(lang, "digital.trustInstant")}</li>
            <li>{tStatic(lang, "digital.trustSupport")}</li>
            <li>{tStatic(lang, "digital.trustGenuine")}</li>
            <li>
              <Link href={localePath(lang, "/stores")}>{tStatic(lang, "digital.trustStores")}</Link>
            </li>
          </ul>
        </div>
      </div>

      {description ? (
        <section class="digital-pdp__about" aria-labelledby="digital-about-heading">
          <h2 id="digital-about-heading">{tStatic(lang, "digital.aboutTitle")}</h2>
          <SanitizedHtml class="pdp-description" html={description} />
        </section>
      ) : null}

      {props.alsoBought.length > 0 ? (
        <section class="digital-pdp__also home-section" aria-labelledby="digital-also-heading">
          <div class="home-section__head">
            <h2 id="digital-also-heading" class="home-section__title">
              {tStatic(lang, "digital.alsoBought")}
            </h2>
          </div>
          <div class="digital-pdp__also-grid">
            {props.alsoBought.map((g) => {
              const href = localePath(lang, `/games/${g.id}?platform=${platform}`);
              const price =
                g.primary_price != null && Number(g.primary_price) > 0
                  ? Number(g.primary_price)
                  : g.secondary_price != null && Number(g.secondary_price) > 0
                    ? Number(g.secondary_price)
                    : null;
              return (
                <Link
                  key={g.id}
                  href={href}
                  prefetch={false}
                  class="digital-pdp__also-card"
                >
                  {g.image_url ? (
                    <img
                      src={g.image_url}
                      alt=""
                      width={160}
                      height={160}
                      loading="lazy"
                      decoding="async"
                    />
                  ) : (
                    <span class="digital-pdp__also-ph" aria-hidden="true" />
                  )}
                  <span class="digital-pdp__also-title">{g.title}</span>
                  {price != null ? (
                    <span class="digital-pdp__also-price">
                      {formatPrice(price, props.currency, lang)}
                    </span>
                  ) : null}
                </Link>
              );
            })}
          </div>
        </section>
      ) : null}

      {props.faqs.length > 0 ? (
        <section class="digital-pdp__faq" aria-labelledby="digital-faq-heading">
          <h2 id="digital-faq-heading">{tStatic(lang, "digital.faqTitle")}</h2>
          <FaqAccordion
            items={props.faqs}
            openIndex={faqOpen.value}
            onToggle$={toggleFaq$}
          />
        </section>
      ) : null}
    </article>
  );
});
