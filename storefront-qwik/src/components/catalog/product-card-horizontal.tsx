import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { StarRating } from "~/components/catalog/star-rating";
import { formatPrice, productPath } from "~/lib/format";
import { useI18n } from "~/lib/i18n/context";
import type { ProductSummary, StoreSettings } from "~/lib/types";

interface ProductCardHorizontalProps {
  product: ProductSummary;
  settings: StoreSettings;
}

/** Compact horizontal product row: image left, title / rating / price right. */
export const ProductCardHorizontal = component$<ProductCardHorizontalProps>(({ product, settings }) => {
  const { locale } = useI18n();
  const pdpUrl = productPath(product, locale);

  return (
    <article class="product-card-h">
      <Link href={pdpUrl} class="product-card-h__media">
        {product.image_url ? (
          <img
            class="product-card-h__image"
            src={product.image_url}
            alt={product.name}
            width={160}
            height={160}
            loading="lazy"
          />
        ) : (
          <div class="product-card-h__image product-card-h__image--empty" aria-hidden="true" />
        )}
      </Link>
      <div class="product-card-h__body">
        <Link href={pdpUrl} class="product-card-h__title-link">
          <h3 class="product-card-h__name">{product.name}</h3>
        </Link>
        <div class="product-card-h__rating">
          <StarRating
            average={product.rating_average ?? 0}
            count={product.rating_count ?? 0}
            size="sm"
            showCount
            showAverage={false}
          />
        </div>
        <div class="product-card-h__prices">
          {product.on_sale && product.compare_at_price != null ? (
            <>
              <span class="product-card-h__price product-card-h__price--sale">
                {formatPrice(product.price, settings.currency, locale)}
              </span>
              <span class="product-card-h__price-compare">
                {formatPrice(product.compare_at_price, settings.currency, locale)}
              </span>
            </>
          ) : (
            <span class="product-card-h__price">
              {formatPrice(product.price, settings.currency, locale)}
            </span>
          )}
        </div>
      </div>
    </article>
  );
});
