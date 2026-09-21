import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import { ProductGallery } from "~/components/catalog/product-gallery";
import { StarRating } from "~/components/catalog/star-rating";
import { ProductShareButtons } from "~/components/catalog/product-share-buttons";
import { DigitalReviews } from "~/components/digital/digital-reviews";
import { FaqAccordion } from "~/components/content/content-blocks";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { JsonLd } from "~/components/seo/json-ld";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { ApiError, checkDigitalGameStock } from "~/lib/api";
import {
  DIGITAL_OFFER_TYPES,
  digitalGalleryUrls,
  digitalOfferEnabled,
  digitalOfferInStock,
  digitalOfferPrice,
  digitalOfferStock,
  digitalReviewsFromGame,
  liveCheckStockIsOut,
  pickDefaultDigitalOffer,
  type DigitalOfferType,
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

function offerI18nKey(offer: DigitalOfferType): string {
  if (offer === "secondary") {
    return "digital.secondary";
  }
  if (offer === "full") {
    return "digital.full";
  }
  return "digital.primary";
}

/** Full shares the Primary POS variation; Accounts allocates with type=full. */
function posSkuForOffer(skus: DigitalSkus, offer: DigitalOfferType): DigitalPosSku | null {
  return offer === "secondary" ? skus.secondary : skus.primary;
}

/**
 * Sigma-style digital game PDP: gallery, offers (incl. Full), reviews, trust.
 * Route-local — do not import from layouts.
 */
export const DigitalGamePdp = component$<DigitalGamePdpProps>((props) => {
  const cart = useCart();
  const nav = useNavigate();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const selected = useSignal<DigitalOfferType>(
    pickDefaultDigitalOffer(props.game, props.platform),
  );
  const pending = useSignal(false);
  const liveOut = useSignal<Partial<Record<DigitalOfferType, boolean>>>({});
  const faqOpen = useSignal<number | null>(null);

  // Confirm Accounts stock after paint so an OOS offer cannot stay clickable.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async () => {
    const gameData = props.game;
    const plat = props.platform;
    const next: Partial<Record<DigitalOfferType, boolean>> = {};
    for (const offer of DIGITAL_OFFER_TYPES) {
      if (!digitalOfferInStock(gameData, plat, offer)) {
        next[offer] = true;
        continue;
      }
      try {
        const stockCheck = await checkDigitalGameStock({
          game_id: Number(gameData.id),
          type: offer,
          platform: plat,
        });
        next[offer] = liveCheckStockIsOut(
          stockCheck.data as { is_available?: boolean; stock?: number | string },
        );
      } catch (e) {
        next[offer] = e instanceof ApiError && e.status === 422;
      }
    }
    liveOut.value = next;
  });

  const game = props.game;
  const platform = props.platform;
  const title = String(game.title ?? "");
  const galleryImages = digitalGalleryUrls(game, platform);
  const image = galleryImages[0] || "";
  const description =
    typeof game.description === "string" && game.description.trim() !== ""
      ? game.description
      : null;
  const reviews = digitalReviewsFromGame(game);

  const offerMeta = DIGITAL_OFFER_TYPES.map((type) => {
    const ok = digitalOfferEnabled(game, platform, type);
    const price = digitalOfferPrice(game, platform, type);
    const inStock =
      digitalOfferInStock(game, platform, type) && !liveOut.value[type];
    return { type, ok, price, inStock };
  });

  // Prefer an in-stock offer as the default when the selection is unavailable.
  let activeOffer: DigitalOfferType = selected.value;
  const activeMeta = offerMeta.find((o) => o.type === activeOffer);
  if (!activeMeta || !activeMeta.ok || activeMeta.price <= 0) {
    const fallback = offerMeta.find((o) => o.ok && o.price > 0);
    if (fallback) {
      activeOffer = fallback.type;
    }
  }
  const active = offerMeta.find((o) => o.type === activeOffer) || offerMeta[0];
  const activeInStock = active?.inStock ?? false;
  const activePrice = active?.price ?? 0;
  const activeOk = Boolean(active?.ok && activePrice > 0);
  const anyOfferOk = offerMeta.some((o) => o.ok && o.price > 0);

  const origin = loc.url.origin;
  const pagePath = localePath(lang, `/games/${String(game.id)}`);
  const pageUrl = `${origin}${pagePath}`;
  const offerLabel = tStatic(lang, offerI18nKey(activeOffer));
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
    let offer: DigitalOfferType = selected.value;
    const available = DIGITAL_OFFER_TYPES.filter(
      (t) =>
        digitalOfferEnabled(gameData, plat, t) &&
        digitalOfferPrice(gameData, plat, t) > 0,
    );
    if (!available.includes(offer) && available.length > 0) {
      offer = available[0];
    }
    const sku = posSkuForOffer(props.skus, offer);
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
      liveOut.value = { ...liveOut.value, [offer]: true };
      await toastError(tStatic(lang, "digital.outOfStock"));
      return;
    }

    const gameTitle = String(gameData.title ?? title);
    const gameImage =
      plat === "5"
        ? String(gameData.ps5_image_url ?? gameData.image_url ?? image)
        : String(gameData.ps4_image_url ?? gameData.image_url ?? image);
    const label = tStatic(lang, offerI18nKey(offer));

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
        liveOut.value = { ...liveOut.value, [offer]: true };
        await toastError(tStatic(lang, "digital.outOfStock"));
        return;
      }
      const digital: CartItemDigital = {
        kind: "game",
        game_id: Number(gameData.id),
        type: offer,
        platform: plat,
        line_key: `ps${plat}_${offer}_stock|game:${gameData.id}`,
        title: `${gameTitle} (${label} · PS${plat})`,
        price,
      };
      await addCartItem(cart, {
        productId: sku.product_id,
        variationId: sku.variation_id,
        slug: null,
        name: digital.title || gameTitle,
        variationName: label,
        price,
        quantity: 1,
        imageUrl: gameImage || sku.image_url,
        digital,
      });
      await toastSuccess(tStatic(lang, "digital.addedToCart"));
      await nav(localePath(lang, "/cart"));
    } catch (e) {
      if (e instanceof ApiError && e.status === 422) {
        liveOut.value = { ...liveOut.value, [offer]: true };
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
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "Product",
          name: title,
          image: galleryImages.length > 0 ? galleryImages : undefined,
          description: description
            ? description.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim()
            : undefined,
          ...(reviews.count > 0
            ? {
                aggregateRating: {
                  "@type": "AggregateRating",
                  ratingValue: reviews.average,
                  reviewCount: reviews.count,
                  bestRating: 5,
                  worstRating: 1,
                },
              }
            : {}),
        }}
      />

      <PageTitleBar
        title={title}
        crumbs={[
          { label: tStatic(lang, "nav.games"), href: "/games" },
          { label: title },
        ]}
      />

      <div class="digital-pdp-page__body">
        <div class="pdp-layout digital-pdp">
          <div class="pdp-gallery--digital">
            <ProductGallery images={galleryImages} alt={title} />
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

            <a href="#product-reviews" class="pdp-rating pdp-rating--link">
              <StarRating
                average={reviews.average}
                count={reviews.count}
                size="sm"
              />
              <span class="pdp-rating__cta">
                {tStatic(lang, "reviews.seeReviews")}
              </span>
            </a>

            {activeOk && activePrice > 0 ? (
              <p class="pdp-price digital-pdp__price">
                {formatPrice(activePrice, props.currency, lang)}
              </p>
            ) : null}

            <div class="digital-pdp__offers" role="list">
              {offerMeta.map(({ type, ok, price, inStock }) =>
                ok && price > 0 ? (
                  <button
                    key={type}
                    type="button"
                    role="listitem"
                    class={`digital-pdp__offer${activeOffer === type ? " digital-pdp__offer--selected" : ""}`}
                    onClick$={() => {
                      selected.value = type;
                    }}
                  >
                    <span class="digital-pdp__offer-row">
                      <span class="digital-pdp__offer-label">
                        {tStatic(lang, offerI18nKey(type))}
                      </span>
                      <span class="digital-pdp__offer-price">
                        {formatPrice(price, props.currency, lang)}
                      </span>
                    </span>
                    <span
                      class={`stock-pill ${inStock ? "stock-pill--in" : "stock-pill--out"}`}
                    >
                      {inStock
                        ? tStatic(lang, "digital.inStock")
                        : tStatic(lang, "digital.outOfStock")}
                    </span>
                  </button>
                ) : null,
              )}
            </div>

            {!anyOfferOk ? (
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
                <Link href={localePath(lang, "/stores")}>
                  {tStatic(lang, "digital.trustStores")}
                </Link>
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

        <DigitalReviews
          gameId={Number(game.id)}
          average={reviews.average}
          count={reviews.count}
          items={reviews.items}
        />

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
                      : g.full_price != null && Number(g.full_price) > 0
                        ? Number(g.full_price)
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
                    <span class="digital-pdp__also-rating">
                      <StarRating
                        average={Number(g.rating_average ?? 0)}
                        count={Number(g.rating_count ?? 0)}
                        size="sm"
                      />
                    </span>
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
      </div>
    </article>
  );
});
