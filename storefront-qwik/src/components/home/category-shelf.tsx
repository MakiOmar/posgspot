import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { HomepageCategoryShelf, ProductSummary, StoreSettings } from "~/lib/types";

interface CategoryShelfProps {
  shelf: HomepageCategoryShelf;
  products: ProductSummary[];
  settings: StoreSettings;
}

/** Side banner + product grid shelf (PS4 / Newest / Parts pattern). */
export const CategoryShelf = component$<CategoryShelfProps>(({ shelf, products, settings }) => {
  const { locale } = useI18n();

  if (!shelf.banner_image_url && products.length === 0) {
    return null;
  }

  const viewMore = shelf.view_more_path || "/products";
  const bannerHref = shelf.banner_link || viewMore;
  const viewMoreAbsolute = viewMore.startsWith("http")
    ? viewMore
    : localePath(locale, viewMore.startsWith("/") ? viewMore : `/${viewMore}`);
  const bannerAbsolute = bannerHref.startsWith("http")
    ? bannerHref
    : localePath(locale, bannerHref.startsWith("/") ? bannerHref : `/${bannerHref}`);

  return (
    <section class="home-section home-category-shelf" aria-label={shelf.title}>
      <div class="home-section__head">
        <h2 class="home-section__title">{shelf.title}</h2>
        {viewMoreAbsolute.startsWith("http") ? (
          <a href={viewMoreAbsolute} class="home-all-products-link">
            {tStatic(locale, "home.viewMore")}
          </a>
        ) : (
          <Link href={viewMoreAbsolute} class="home-all-products-link">
            {tStatic(locale, "home.viewMore")}
          </Link>
        )}
      </div>
      <div class="home-category-shelf__layout">
        {shelf.banner_image_url ? (
          bannerAbsolute.startsWith("http") ? (
            <a href={bannerAbsolute} class="home-category-shelf__banner">
              <img src={shelf.banner_image_url} alt="" loading="lazy" />
            </a>
          ) : (
            <Link href={bannerAbsolute} class="home-category-shelf__banner">
              <img src={shelf.banner_image_url} alt="" loading="lazy" />
            </Link>
          )
        ) : null}
        {products.length > 0 ? (
          <div class="home-category-shelf__grid">
            {products.map((product) => (
              <ProductCard key={product.id} product={product} settings={settings} />
            ))}
          </div>
        ) : (
          <div class="home-category-shelf__empty empty-state">
            {tStatic(locale, "catalog.noProducts")}
          </div>
        )}
      </div>
    </section>
  );
});
