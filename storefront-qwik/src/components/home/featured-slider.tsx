import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { HomeCarousel } from "~/components/home/home-carousel";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { ProductSummary, StoreSettings } from "~/lib/types";

interface FeaturedSliderProps {
  products: ProductSummary[];
  settings: StoreSettings;
}

/** Featured / deals product carousel (products marked is_storefront_featured). */
export const FeaturedSlider = component$<FeaturedSliderProps>(({ products, settings }) => {
  const { locale } = useI18n();

  if (products.length === 0) {
    return null;
  }

  return (
    <section class="home-section home-featured" aria-labelledby="home-featured-heading">
      <div class="home-section__head">
        <h2 id="home-featured-heading" class="home-section__title">
          {tStatic(locale, "home.featured")}
        </h2>
        <Link href={localePath(locale, "/products")} class="home-all-products-link">
          {tStatic(locale, "footer.allProducts")}
        </Link>
      </div>
      <HomeCarousel
        label={tStatic(locale, "home.featured")}
        trackClass="home-product-rail"
      >
        {products.map((product) => (
          <div key={product.id} class="home-product-rail__item">
            <ProductCard product={product} settings={settings} />
          </div>
        ))}
      </HomeCarousel>
    </section>
  );
});
