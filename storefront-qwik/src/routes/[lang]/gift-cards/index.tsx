import { $, component$, useSignal } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { GiftCardArt } from "~/components/digital/gift-card-art";
import { GiftCardBrandTiles } from "~/components/digital/gift-card-brand-tiles";
import { GiftCardCountryModal } from "~/components/digital/gift-card-country-modal";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { addCartItem } from "~/lib/cart-actions";
import { notifyAddedToCart } from "~/lib/cart-feedback";
import { useCart } from "~/lib/cart-context";
import { checkDigitalCardStock, fetchDigitalCardCategories } from "~/lib/api";
import { formatPrice } from "~/lib/format";
import { filterGiftCards, groupGiftCards, hasClassifications } from "~/lib/gift-card-groups";
import { brandDisplayLabel, countryDisplayLabel } from "~/lib/gift-card-labels";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError } from "~/lib/notify";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CartItemDigital, DigitalCardCategory } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useGiftCards = routeLoader$(async ({ params, redirect }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchDigitalCardCategories(locale);
    // Strip nested Accounts fields (e.g. cards[]) so Qwik can serialize across $.
    const categories: DigitalCardCategory[] = (data.categories ?? []).map((row) => ({
      id: Number(row.id),
      name: String(row.name ?? ""),
      price: row.price,
      poster_image: row.poster_image ?? null,
      card_art: row.card_art ?? null,
      classification: row.classification ?? null,
      pos_sku: row.pos_sku ?? null,
    }));
    return {
      categories,
      skus: data.skus,
    };
  } catch (e: unknown) {
    const status = typeof e === "object" && e && "status" in e ? Number((e as { status: number }).status) : 0;
    if (status === 503) {
      throw redirect(302, localePath(locale, "/products"));
    }
    return {
      categories: [] as DigitalCardCategory[],
      skus: { primary: null, secondary: null, gift_card: null },
    };
  }
});

export default component$(() => {
  const list = useGiftCards();
  const settings = useSiteSettings();
  const cart = useCart();
  const nav = useNavigate();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const basePath = localePath(lang, "/gift-cards");
  const browse = resolveBrowse(list.value.categories, loc.url.searchParams);
  const categories = browse.categories;
  const pendingId = useSignal<number | null>(null);
  const modalBrand = useSignal<string | null>(null);
  const modalGroup = browse.groups.find((group) => group.brand === modalBrand.value) ?? null;
  const brandLabel = browse.brandGroup ? brandDisplayLabel(lang, browse.brandGroup.brand, browse.brandGroup.label) : "";
  const countryLabel = browse.brandGroup && browse.country
    ? countryDisplayLabel(
        lang,
        browse.country,
        browse.brandGroup.countries.find((country) => country.key === browse.country)?.label ?? browse.country,
      )
    : tStatic(lang, "digital.allCountries");

  const addCard$ = $(async (categoryId: number, name: string, priceRaw: number | string, posterImage: string | null) => {
    const synced = list.value.categories.find((category) => category.id === categoryId)?.pos_sku;
    const sku = synced ?? list.value.skus.gift_card;
    if (!sku) {
      await toastError(tStatic(lang, "digital.skuMissing"));
      return;
    }
    const price = Number(priceRaw);
    if (!Number.isFinite(price) || price <= 0) {
      await toastError(tStatic(lang, "digital.unavailable"));
      return;
    }

    pendingId.value = categoryId;
    try {
      await checkDigitalCardStock(categoryId);
      const digital: CartItemDigital = {
        kind: "card",
        card_category_id: categoryId,
        line_key: `card|category:${categoryId}`,
        title: name,
        price,
      };
      await addCartItem(cart, {
        productId: sku.product_id,
        variationId: sku.variation_id,
        slug: null,
        name,
        variationName: tStatic(lang, "digital.giftCard"),
        price,
        quantity: 1,
        imageUrl: posterImage || sku.image_url,
        digital,
      });
      notifyAddedToCart(lang, name, nav);
    } catch (e) {
      await toastError(
        e instanceof Error ? e.message : tStatic(lang, "digital.stockFailed"),
      );
    } finally {
      pendingId.value = null;
    }
  });

  return (
    <section class="digital-catalog">
      <PageTitleBar
        title={browse.brandGroup ? brandLabel : tStatic(lang, "digital.giftCardsTitle")}
        crumbs={
          browse.brandGroup
            ? [{ label: tStatic(lang, "nav.giftCards"), href: "/gift-cards" }, { label: brandLabel }, { label: countryLabel }]
            : [{ label: tStatic(lang, "nav.giftCards") }]
        }
      />

      <header class="digital-catalog__header">
        <p class="footer-muted digital-catalog__lead">{tStatic(lang, "digital.giftCardsLead")}</p>
      </header>

      {/* Brand -> country selection bar above a filtered grid */}
      {browse.brandGroup ? (
        <nav class="gift-card-filter" aria-label={tStatic(lang, "digital.chooseCountry")}>
          <Link href={basePath}>{tStatic(lang, "digital.allBrands")}</Link>
          <span aria-hidden="true">›</span>
          <strong>{brandLabel}</strong>
          <span aria-hidden="true">›</span>
          <strong>{countryLabel}</strong>
          {browse.brandGroup.countries.length > 1 ? (
            <button
              type="button"
              class="btn btn-secondary gift-card-filter__change"
              onClick$={() => (modalBrand.value = browse.brandGroup?.brand ?? null)}
            >
              {tStatic(lang, "digital.changeCountry")}
            </button>
          ) : null}
        </nav>
      ) : browse.showAll ? (
        <nav class="gift-card-filter" aria-label={tStatic(lang, "digital.chooseBrand")}>
          <Link href={basePath}>‹ {tStatic(lang, "digital.allBrands")}</Link>
        </nav>
      ) : null}

      {browse.browsing ? (
        <>
          <div class="gift-card-brands__head">
            <h2 class="gift-card-brands__title">{tStatic(lang, "digital.chooseBrand")}</h2>
            <Link class="btn btn-secondary" href={`${basePath}?all=1`}>
              {tStatic(lang, "digital.allCards")}
            </Link>
          </div>
          <GiftCardBrandTiles
            groups={browse.groups}
            lang={lang}
            basePath={basePath}
            onChoose$={(brand: string) => {
              modalBrand.value = brand;
            }}
          />
        </>
      ) : categories.length === 0 ? (
        <div class="empty-state">
          {list.value.categories.length > 0 ? tStatic(lang, "digital.noCardsForSelection") : tStatic(lang, "digital.noGiftCards")}
        </div>
      ) : (
        <div class="product-grid digital-catalog__grid">
          {categories.map((category) => (
            <article key={category.id} class="product-card digital-game-card">
              <div class="product-card__media digital-game-card__media">
                {category.poster_image ? (
                  <img
                    class="product-card__image digital-game-card__image"
                    src={category.poster_image}
                    alt={category.name}
                    width={320}
                    height={320}
                    loading="lazy"
                  />
                ) : category.card_art ? (
                  /* No poster: artwork generated from the product name */
                  <GiftCardArt art={category.card_art} />
                ) : (
                  <div class="product-card__image digital-game-card__image" aria-hidden="true" />
                )}
              </div>
              <div class="product-card__body digital-game-card__body">
                <h2 class="product-card__name digital-game-card__title">{category.name}</h2>
                <p class="product-card__price digital-game-card__price">
                  {formatPrice(Number(category.price), settings.value.currency, lang)}
                </p>
                <button
                  type="button"
                  class="btn btn-primary digital-game-card__cta"
                  disabled={pendingId.value !== null}
                  onClick$={() =>
                    addCard$(
                      category.id,
                      category.name,
                      category.price,
                      category.poster_image ?? null,
                    )
                  }
                >
                  {pendingId.value === category.id
                    ? tStatic(lang, "digital.adding")
                    : tStatic(lang, "digital.addGiftCard")}
                </button>
              </div>
            </article>
          ))}
        </div>
      )}

      <GiftCardCountryModal
        group={modalGroup}
        lang={lang}
        basePath={basePath}
        onClose$={() => {
          modalBrand.value = null;
        }}
      />
    </section>
  );
});

/**
 * URL state: `?q=` searches, `?all=1` lists everything, `?brand=&country=` narrows; otherwise brand tiles.
 * Unknown brand/country values fall back to the tiles / all countries.
 */
function resolveBrowse(all: DigitalCardCategory[], params: URLSearchParams) {
  const q = (params.get("q") || "").trim().toLowerCase();
  const searched = q ? all.filter((category) => category.name.toLowerCase().includes(q)) : all;
  const groups = groupGiftCards(searched);
  const brandGroup = q ? undefined : groups.find((group) => group.brand === params.get("brand"));
  let country = brandGroup?.countries.find((option) => option.key === params.get("country"))?.key ?? null;
  if (brandGroup && !country && brandGroup.countries.length === 1) {
    country = brandGroup.countries[0].key;
  }
  const tiles = !q && !brandGroup && hasClassifications(all);
  const showAll = tiles && params.get("all") === "1";

  return {
    groups,
    brandGroup,
    country,
    browsing: tiles && !showAll,
    showAll,
    categories: brandGroup ? filterGiftCards(searched, brandGroup.brand, country) : searched,
  };
}

export const head: DocumentHead = ({ resolveValue, url }) => {
  const lang = resolveValue(useLangParam);
  const settings = resolveValue(useSiteSettings);
  const brandGroup = resolveBrowse(resolveValue(useGiftCards).categories, url.searchParams).brandGroup;
  // Filtered views keep the /gift-cards canonical; only the title names the brand.
  const title = brandGroup
    ? tStatic(lang, "digital.giftCardsBrandSeoTitle", {
        brand: brandDisplayLabel(lang, brandGroup.brand, brandGroup.label),
        businessName: settings.business_name,
      })
    : tStatic(lang, "digital.giftCardsSeoTitle", { businessName: settings.business_name });
  return withStorefrontThemeHead(
    {
      title,
      meta: [
        {
          name: "description",
          content: tStatic(lang, "digital.giftCardsSeoDescription", {
            businessName: settings.business_name,
          }),
        },
      ],
      links: publicSeoLinks(url.origin, "/gift-cards", lang),
    },
    settings,
  );
};
