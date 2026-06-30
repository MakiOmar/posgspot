import { $, component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice, productPath } from "~/lib/format";
import type { ProductSummary, StoreSettings } from "~/lib/types";

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
  const pdpUrl = productPath(product);
  const badge = saleBadgeLabel(product, settings);
  const hasOptions = product.has_options;

  const addToCart$ = $(() => {
    if (!product.variation_id || !product.in_stock) {
      return;
    }

    addCartItem(cart, {
      productId: product.id,
      variationId: product.variation_id,
      slug: product.slug,
      name: product.name,
      variationName: product.variation_name || "DUMMY",
      price: product.price,
      quantity: 1,
      imageUrl: product.image_url,
    });
  });

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
                {formatPrice(product.price, settings.currency)}
              </span>
              <span class="product-card__price-compare">
                {formatPrice(product.compare_at_price, settings.currency)}
              </span>
            </>
          ) : (
            <span class="product-card__price">
              {formatPrice(product.price, settings.currency)}
            </span>
          )}
        </div>

        <span
          class={`stock-pill ${product.in_stock ? "stock-pill--in" : "stock-pill--out"}`}
        >
          {product.in_stock ? "In stock" : "Out of stock"}
        </span>

        {hasOptions ? (
          <Link href={pdpUrl} class="btn btn-secondary btn-block product-card__action">
            View options
          </Link>
        ) : (
          <button
            type="button"
            class="btn btn-primary btn-block product-card__action"
            disabled={!product.in_stock || !product.variation_id}
            onClick$={addToCart$}
          >
            Add to cart
          </button>
        )}
      </div>
    </article>
  );
});
