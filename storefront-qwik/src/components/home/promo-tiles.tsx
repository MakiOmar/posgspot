import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { HomepagePromoTile } from "~/lib/types";

interface PromoTilesProps {
  tiles: HomepagePromoTile[];
}

/**
 * Promo tile grid after the hero (tiles from GET /homepage section settings).
 * Layout: tall main tile + wide top-right + two smaller tiles (CSS grid).
 */
export const PromoTiles = component$<PromoTilesProps>(({ tiles }) => {
  const { locale } = useI18n();

  if (tiles.length === 0) {
    return null;
  }

  return (
    <section class="home-promo-tiles" aria-labelledby="home-promo-heading">
      <div class="home-section__head">
        <div>
          <p class="home-promo-tiles__eyebrow">{tStatic(locale, "home.promoEyebrow")}</p>
          <h2 id="home-promo-heading" class="home-section__title">
            {tStatic(locale, "home.promoTitle")}
          </h2>
        </div>
      </div>
      <div class="home-promo-tiles__grid">
        {tiles.map((tile, i) => (
          <Link
            key={tile.id}
            href={localePath(locale, tile.href)}
            class={[
              "home-promo-tiles__tile",
              i === 0 ? "home-promo-tiles__tile--main" : "",
              i === 1 ? "home-promo-tiles__tile--wide" : "",
            ]
              .filter(Boolean)
              .join(" ")}
          >
            <img
              class="home-promo-tiles__img"
              src={tile.image_url}
              alt={tile.label || ""}
              width={i === 0 ? 800 : 400}
              height={i === 0 ? 600 : 300}
              loading="lazy"
            />
            <span class="home-promo-tiles__cta">{tStatic(locale, "home.shopNow")}</span>
          </Link>
        ))}
      </div>
    </section>
  );
});
