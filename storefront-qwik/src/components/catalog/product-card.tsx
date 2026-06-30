import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { formatPrice, productPath } from "~/lib/format";
import type { ProductSummary, StoreSettings } from "~/lib/types";

interface ProductCardProps {
  product: ProductSummary;
  settings: StoreSettings;
}

export const ProductCard = component$<ProductCardProps>(({ product, settings }) => {
  return (
    <article class="product-card">
      <Link href={productPath(product)}>
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
        <div class="product-card__body">
          <h2 class="product-card__name">{product.name}</h2>
          <div class="product-card__price">{formatPrice(product.price, settings.currency)}</div>
          <span
            class={`stock-pill ${product.in_stock ? "stock-pill--in" : "stock-pill--out"}`}
          >
            {product.in_stock ? "In stock" : "Out of stock"}
          </span>
        </div>
      </Link>
    </article>
  );
});
