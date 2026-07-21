import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { Brand } from "~/lib/types";

interface BrandSliderProps {
  brands: Brand[];
  limit?: number;
}

/** Shop-by-brand logo strip. */
export const BrandSlider = component$<BrandSliderProps>(({ brands, limit = 16 }) => {
  const { locale } = useI18n();
  const items = brands.filter((b) => Boolean(b.slug)).slice(0, limit);

  if (items.length === 0) {
    return null;
  }

  return (
    <section class="home-section home-brands" aria-labelledby="home-brands-heading">
      <div class="home-section__head">
        <h2 id="home-brands-heading" class="home-section__title">
          {tStatic(locale, "home.shopByBrand")}
        </h2>
        <Link href={localePath(locale, "/brands")} class="home-all-products-link">
          {tStatic(locale, "nav.brands")}
        </Link>
      </div>
      <div class="home-brands__rail">
        {items.map((brand) => (
          <Link
            key={brand.id}
            href={localePath(locale, `/brands/${encodeURIComponent(brand.slug)}`)}
            class="home-brands__card"
            title={brand.name}
          >
            {brand.image_url && !brand.image_url.includes("default.png") ? (
              <img
                src={brand.image_url}
                alt={brand.name}
                class="home-brands__img"
                width={160}
                height={80}
                loading="lazy"
              />
            ) : (
              <span class="home-brands__placeholder">{brand.name.slice(0, 1)}</span>
            )}
            <span class="home-brands__name">{brand.name}</span>
          </Link>
        ))}
      </div>
    </section>
  );
});
