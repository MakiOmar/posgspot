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

function resolveStorefrontHref(path: string, locale: "en" | "ar"): string {
  if (path.startsWith("http://") || path.startsWith("https://")) {
    return path;
  }
  return localePath(locale, path.startsWith("/") ? path : `/${path}`);
}

/** Side banner + product grid shelf driven by POS category homepage-shelf fields. */
export const CategoryShelf = component$<CategoryShelfProps>(({ shelf, products, settings }) => {
  const { locale } = useI18n();

  if (!shelf.banner_image_url && products.length === 0) {
    return null;
  }

  const viewMoreHref = resolveStorefrontHref(shelf.view_more_path || `/category/${shelf.slug}`, locale);
  const bannerHref = resolveStorefrontHref(shelf.banner_link || shelf.view_more_path || `/category/${shelf.slug}`, locale);
  const viewMoreLabel = shelf.view_more_label || tStatic(locale, "home.viewMore");
  const buttonText = shelf.button_text || tStatic(locale, "home.shopNow");
  const externalBanner = bannerHref.startsWith("http");
  const externalMore = viewMoreHref.startsWith("http");

  const bannerInner = (
    <>
      {shelf.banner_image_url ? (
        <img
          src={shelf.banner_image_url}
          alt=""
          width={400}
          height={640}
          loading="lazy"
          class="home-category-shelf__banner-img"
        />
      ) : (
        <span class="home-category-shelf__banner-fallback" aria-hidden="true" />
      )}
      {(shelf.banner_kicker || shelf.banner_text || shelf.button_text) && (
        <span class="home-category-shelf__banner-copy">
          {shelf.banner_kicker ? (
            <span class="home-category-shelf__banner-kicker">{shelf.banner_kicker}</span>
          ) : null}
          {shelf.banner_text ? (
            <span class="home-category-shelf__banner-text">{shelf.banner_text}</span>
          ) : null}
          {shelf.button_text || shelf.banner_text ? (
            <span class="home-category-shelf__banner-btn">{buttonText}</span>
          ) : null}
        </span>
      )}
    </>
  );

  return (
    <section class="home-section home-category-shelf" aria-label={shelf.heading}>
      <div class="home-section__head">
        <h2 class="home-section__title">{shelf.heading}</h2>
        {externalMore ? (
          <a href={viewMoreHref} class="home-all-products-link">
            {viewMoreLabel}
          </a>
        ) : (
          <Link href={viewMoreHref} class="home-all-products-link">
            {viewMoreLabel}
          </Link>
        )}
      </div>
      <div class="home-category-shelf__layout">
        {externalBanner ? (
          <a href={bannerHref} class="home-category-shelf__banner">
            {bannerInner}
          </a>
        ) : (
          <Link href={bannerHref} class="home-category-shelf__banner">
            {bannerInner}
          </Link>
        )}
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
