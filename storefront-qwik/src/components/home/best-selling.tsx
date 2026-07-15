import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { ProductSummary, StoreSettings } from "~/lib/types";

interface BestSellingProps {
  products: ProductSummary[];
  settings: StoreSettings;
}

/** Best-selling products grid (sort=bestsellers). */
export const BestSelling = component$<BestSellingProps>(({ products, settings }) => {
  const { locale } = useI18n();

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
      <div class="product-grid">
        {products.map((product) => (
          <ProductCard key={product.id} product={product} settings={settings} />
        ))}
      </div>
    </section>
  );
});
