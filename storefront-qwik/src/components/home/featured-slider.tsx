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
      <HomeCarousel
        label={tStatic(locale, "home.featured")}
        title={tStatic(locale, "home.featured")}
        titleId="home-featured-heading"
        trackClass="home-product-rail"
      >
        <Link
          q:slot="action"
          href={localePath(locale, "/products")}
          class="home-all-products-link"
          prefetch={false}
        >
          {tStatic(locale, "footer.allProducts")}
        </Link>
        {products.map((product) => (
          <div key={product.id} class="home-product-rail__item">
            <ProductCard product={product} settings={settings} />
          </div>
        ))}
      </HomeCarousel>
    </section>
  );
});
