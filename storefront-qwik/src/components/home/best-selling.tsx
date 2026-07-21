import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { ProductCardHorizontal } from "~/components/catalog/product-card-horizontal";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { ProductSummary, StoreSettings } from "~/lib/types";

export type BestsellersStyle = "grid" | "horizontal";

interface BestSellingProps {
  products: ProductSummary[];
  settings: StoreSettings;
  /** Layout from homepage section settings (`style`). */
  style?: BestsellersStyle | string;
}

/** Best-selling products — grid cards or horizontal image+details cards. */
export const BestSelling = component$<BestSellingProps>(({ products, settings, style = "grid" }) => {
  const { locale } = useI18n();
  const layout: BestsellersStyle = style === "horizontal" ? "horizontal" : "grid";

  if (products.length === 0) {
    return null;
  }

  return (
    <section class="home-section home-bestsellers" aria-labelledby="home-bestsellers-heading">
      <div class="home-section__head">
        <h2 id="home-bestsellers-heading" class="home-section__title">
          {tStatic(locale, "home.bestSelling")}
        </h2>
        <Link href={localePath(locale, "/products")} class="home-all-products-link">
          {tStatic(locale, "home.viewMore")}
        </Link>
      </div>
      {layout === "horizontal" ? (
        <div class="home-bestsellers__horizontal">
          {products.map((product) => (
            <ProductCardHorizontal key={product.id} product={product} settings={settings} />
          ))}
        </div>
      ) : (
        <div class="product-grid">
          {products.map((product) => (
            <ProductCard key={product.id} product={product} settings={settings} />
          ))}
        </div>
      )}
    </section>
  );
});
