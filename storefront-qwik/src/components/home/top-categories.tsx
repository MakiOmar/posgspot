import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { HomeCarousel } from "~/components/home/home-carousel";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { Category } from "~/lib/types";

interface TopCategoriesProps {
  categories: Category[];
  limit?: number;
}

/** Top category cards carousel with thumbnail or CSS placeholder. */
export const TopCategories = component$<TopCategoriesProps>(({ categories, limit = 8 }) => {
  const { locale } = useI18n();
  const items = categories.filter((c) => Boolean(c.slug)).slice(0, limit);

  if (items.length === 0) {
    return null;
  }

  return (
    <section class="home-section home-top-categories" aria-labelledby="home-top-cats-heading">
      <HomeCarousel
        label={tStatic(locale, "home.topCategories")}
        title={tStatic(locale, "home.topCategories")}
        titleId="home-top-cats-heading"
        trackClass="home-top-categories__rail"
      >
        <Link
          q:slot="action"
          href={localePath(locale, "/products")}
          class="home-all-products-link"
          prefetch={false}
        >
          {tStatic(locale, "footer.allProducts")}
        </Link>
        {items.map((category) => (
          <Link
            key={category.id}
            href={localePath(locale, `/category/${encodeURIComponent(category.slug!)}`)}
            class="home-top-categories__card"
            prefetch={false}
          >
            <span class="home-top-categories__media">
              {category.image_url && !category.image_url.includes("default.png") ? (
                <img
                  src={category.image_url}
                  alt=""
                  class="home-top-categories__img"
                  width={200}
                  height={200}
                  loading="lazy"
                />
              ) : (
                <span class="home-top-categories__placeholder" aria-hidden="true" />
              )}
            </span>
            <span class="home-top-categories__name">{category.name}</span>
          </Link>
        ))}
      </HomeCarousel>
    </section>
  );
});
