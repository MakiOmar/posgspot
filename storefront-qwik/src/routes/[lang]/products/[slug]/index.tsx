import { component$, useSignal } from "@builder.io/qwik";
import { routeLoader$, useLocation, type DocumentHead } from "@builder.io/qwik-city";
import { AvailabilityCheckButton } from "~/components/catalog/availability-check-button";
import { ProductCard } from "~/components/catalog/product-card";
import {
  galleryImagesForVariation,
  ProductGallery,
} from "~/components/catalog/product-gallery";
import { WishlistToggle } from "~/components/catalog/wishlist-toggle";
import { Breadcrumbs } from "~/components/seo/breadcrumbs";
import { JsonLd } from "~/components/seo/json-ld";
import { QuantityStepper } from "~/components/ui/quantity-stepper";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { fetchProduct } from "~/lib/api";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import {
  breadcrumbListJsonLd,
  canonicalUrl,
  publicSeoLinks,
} from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { ProductVariation } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { productSummaryFromDetail } from "~/lib/wishlist-actions";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useProductDetail = routeLoader$(async ({ params, status }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchProduct(params.slug, locale);
    return data;
  } catch {
    throw status(404);
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const product = useProductDetail();
  const cart = useCart();
  const pending = usePendingState();
  const loc = useLocation();
  const { locale } = useI18n();
  const origin = loc.url.origin;

  const selectedVariation = useSignal<ProductVariation>(
    product.value.variations[0] || {
      id: 0,
      name: "",
      sub_sku: "",
      price: 0,
      in_stock: false,
      qty_available: 0,
      images: [],
    },
  );
  const quantity = useSignal(1);
  const adding = useSignal(false);

  const p = product.value;
  const currency = settings.value.currency;
  const variation = selectedVariation.value;
  const galleryImages = galleryImagesForVariation(p.images, variation.images);
  const stockMax =
    p.enable_stock && variation.in_stock && variation.qty_available > 0
      ? Math.floor(variation.qty_available)
      : undefined;

  const categoryPath = p.category?.slug
    ? `/category/${encodeURIComponent(p.category.slug)}`
    : null;

  const breadcrumbItems = [
    { label: tStatic(locale, "nav.home"), href: localePath(locale, "/") },
    { label: tStatic(locale, "nav.shop"), href: localePath(locale, "/products") },
    ...(p.category && categoryPath
      ? [{ label: p.category.name, href: localePath(locale, categoryPath) }]
      : []),
    { label: p.name },
  ];

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "Product",
          name: p.name,
          sku: p.sku,
          image: galleryImages,
          description: p.description || undefined,
          offers: {
            "@type": "Offer",
            price: selectedVariation.value.price,
            priceCurrency: currency.code,
            availability: selectedVariation.value.in_stock
              ? "https://schema.org/InStock"
              : "https://schema.org/OutOfStock",
          },
        }}
      />
      <JsonLd
        data={breadcrumbListJsonLd(
          breadcrumbItems.map((item) => ({
            name: item.label,
            item: item.href ? `${origin}${item.href}` : undefined,
          })),
        )}
      />

      <article class="pdp">
        <div class="pdp-top">
          <Breadcrumbs items={breadcrumbItems} />
        </div>

        <div class="pdp-layout">
          <ProductGallery images={galleryImages} alt={p.name} />

          <div class="pdp-info">
            <h1>{p.name}</h1>
            {p.brand ? (
              <p class="footer-muted">
                {tStatic(locale, "catalog.brand")}: {p.brand.name}
              </p>
            ) : null}
            {p.category ? (
              <p class="footer-muted">
                {tStatic(locale, "catalog.category")}: {p.category.name}
              </p>
            ) : null}

            <div class="pdp-price">
              {selectedVariation.value.on_sale &&
              selectedVariation.value.compare_at_price != null ? (
                <>
                  <span class="product-card__price product-card__price--sale">
                    {formatPrice(selectedVariation.value.price, currency, locale)}
                  </span>
                  <span class="product-card__price-compare" style={{ marginInlineStart: "0.5rem" }}>
                    {formatPrice(selectedVariation.value.compare_at_price, currency, locale)}
                  </span>
                </>
              ) : (
                formatPrice(selectedVariation.value.price, currency, locale)
              )}
            </div>

            {p.variations.length > 1 ? (
              <label>
                <span class="footer-muted">{tStatic(locale, "catalog.variation")}</span>
                <select
                  class="pdp-select"
                  onChange$={(event) => {
                    const id = Number((event.target as HTMLSelectElement).value);
                    const found = p.variations.find((v) => v.id === id);
                    if (found) {
                      selectedVariation.value = found;
                      quantity.value = 1;
                    }
                  }}
                >
                  {p.variations.map((v) => (
                    <option key={v.id} value={v.id} selected={v.id === selectedVariation.value.id}>
                      {`${v.name} — ${formatPrice(v.price, currency, locale)}`}
                    </option>
                  ))}
                </select>
              </label>
            ) : null}

            <span
              class={`stock-pill ${selectedVariation.value.in_stock ? "stock-pill--in" : "stock-pill--out"}`}
            >
              {selectedVariation.value.in_stock
                ? tStatic(locale, "catalog.inStock")
                : tStatic(locale, "catalog.outOfStock")}
            </span>

            {p.description ? (
              <SanitizedHtml
                class="pdp-description"
                html={p.description}
              />
            ) : null}

            <div class="pdp-actions">
              <WishlistToggle
                product={productSummaryFromDetail(p, selectedVariation.value)}
                variant="inline"
                class="pdp-wishlist-toggle"
              />
              <div class="pdp-qty-field">
                <span class="footer-muted">{tStatic(locale, "catalog.qty")}</span>
                <QuantityStepper
                  value={quantity.value}
                  max={stockMax}
                  disabled={!variation.in_stock}
                  label={tStatic(locale, "a11y.quantityFor", { name: p.name })}
                  onChange$={(next) => {
                    quantity.value = next;
                  }}
                />
              </div>
              <button
                type="button"
                class={`btn btn-primary${!variation.in_stock ? " btn--disabled" : ""}`}
                onClick$={async () => {
                  const variation = selectedVariation.value;
                  if (!variation.id || !variation.in_stock) {
                    return;
                  }
                  await withPendingFeedback(pending, adding, async () => {
                    await addCartItem(cart, {
                      productId: product.value.id,
                      variationId: variation.id,
                      slug: product.value.slug,
                      name: product.value.name,
                      variationName: variation.name,
                      price: variation.price,
                      quantity: quantity.value,
                      imageUrl: product.value.images[0] || null,
                    });
                  });
                }}
                disabled={!variation.in_stock || adding.value}
                aria-disabled={!variation.in_stock || adding.value}
              >
                {adding.value
                  ? tStatic(locale, "catalog.addingToCart")
                  : tStatic(locale, "catalog.addToCart")}
              </button>
              {variation.id ? (
                <AvailabilityCheckButton productId={p.id} variationId={variation.id} />
              ) : null}
            </div>
          </div>
        </div>

        {(p.related_products?.length ?? 0) > 0 ? (
          <section class="pdp-related home-section" aria-labelledby="pdp-related-heading">
            <div class="home-section__head">
              <h2 id="pdp-related-heading" class="home-section__title">
                {tStatic(locale, "catalog.relatedProducts")}
              </h2>
            </div>
            <div class="product-grid">
              {p.related_products!.map((related) => (
                <ProductCard key={related.id} product={related} settings={settings.value} />
              ))}
            </div>
          </section>
        ) : null}
      </article>
    </>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const product = resolveValue(useProductDetail);
  const lang = resolveValue(useLangParam);
  const title = `${product.name} — ${settings.business_name}`;
  const description =
    product.description?.replace(/<[^>]+>/g, "").slice(0, 160) ||
    tStatic(lang, "seo.productDescription", {
      name: product.name,
      businessName: settings.business_name,
    });
  const image = product.images[0] || undefined;
  const path = `/products/${product.slug || product.id}`;
  const canonical = canonicalUrl(url.origin, path, lang);

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "product" },
        { property: "og:url", content: canonical },
        ...(image ? [{ property: "og:image", content: image }] : []),
        { name: "twitter:card", content: "summary_large_image" },
      ],
      links: publicSeoLinks(url.origin, path, lang),
    },
    settings,
  );
};
