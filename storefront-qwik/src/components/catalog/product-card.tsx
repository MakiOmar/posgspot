import { component$, useSignal } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { AvailabilityCheckButton } from "~/components/catalog/availability-check-button";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice, productPath } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { usePendingState } from "~/lib/pending-context";
import type { ProductSummary, StoreSettings } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";

interface ProductCardProps {
  product: ProductSummary;
  settings: StoreSettings;
}

function saleBadgeLabel(product: ProductSummary, settings: StoreSettings): string | null {
  if (!product.on_sale) {
    return null;
  }

  if (settings.sale_badge.mode === "text") {
    return settings.sale_badge.text || "Sale";
  }

  if (product.sale_percent > 0) {
    return `-${product.sale_percent}%`;
  }

  return settings.sale_badge.text || "Sale";
}

export const ProductCard = component$<ProductCardProps>(({ product, settings }) => {
  const cart = useCart();
  const pending = usePendingState();
  const adding = useSignal(false);
  const { locale } = useI18n();
  const pdpUrl = productPath(product, locale);
  const badge = saleBadgeLabel(product, settings);
  const hasOptions = product.has_options;
  const outOfStock = !product.in_stock;
  const showCardAvailability =
    outOfStock &&
    (settings.catalog?.show_availability_on_cards ?? true) &&
    product.variation_id != null;
  const showActions = showCardAvailability || !outOfStock;

  return (
    <article class="product-card">
      <Link href={pdpUrl} class="product-card__media">
        {badge ? <span class="product-card__sale-badge">{badge}</span> : null}
        {product.image_url ? (
          <img
            class="product-card__image"
            src={product.image_url}
            alt={product.name}
            width={320}
            height={320}
            loading="lazy"
          />
        ) : (
          <div class="product-card__image" aria-hidden="true" />
        )}
      </Link>

      <div class="product-card__body">
        <Link href={pdpUrl} class="product-card__title-link">
          <h2 class="product-card__name">{product.name}</h2>
        </Link>

        <div class="product-card__prices">
          {product.on_sale && product.compare_at_price != null ? (
            <>
              <span class="product-card__price product-card__price--sale">
                {formatPrice(product.price, settings.currency, locale)}
              </span>
              <span class="product-card__price-compare">
                {formatPrice(product.compare_at_price, settings.currency, locale)}
              </span>
            </>
          ) : (
            <span class="product-card__price">
              {formatPrice(product.price, settings.currency, locale)}
            </span>
          )}
        </div>

        <span
          class={`stock-pill ${product.in_stock ? "stock-pill--in" : "stock-pill--out"}`}
        >
          {product.in_stock ? tStatic(locale, "catalog.inStock") : tStatic(locale, "catalog.outOfStock")}
        </span>

        {showActions ? (
          <div class="product-card__actions">
            {showCardAvailability ? (
              <AvailabilityCheckButton
                productId={product.id}
                variationId={product.variation_id!}
                block
                class="product-card__action"
              />
            ) : hasOptions ? (
              <Link href={pdpUrl} class="btn btn-secondary btn-block product-card__action">
                {tStatic(locale, "catalog.viewOptions")}
              </Link>
            ) : (
              <button
                type="button"
                class="btn btn-primary btn-block product-card__action"
                disabled={!product.variation_id || adding.value}
                aria-disabled={!product.variation_id || adding.value}
                onClick$={async () => {
                  const variationId = product.variation_id;
                  if (!variationId) {
                    return;
                  }
                  await withPendingFeedback(pending, adding, async () => {
                    await addCartItem(cart, {
                      productId: product.id,
                      variationId,
                      slug: product.slug,
                      name: product.name,
                      variationName: product.variation_name || "DUMMY",
                      price: product.price,
                      quantity: 1,
                      imageUrl: product.image_url,
                    });
                  });
                }}
              >
                {adding.value ? tStatic(locale, "catalog.addingToCart") : tStatic(locale, "catalog.addToCart")}
              </button>
            )}
          </div>
        ) : null}
      </div>
    </article>
  );
});
