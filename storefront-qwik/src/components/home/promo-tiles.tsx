import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { HOME_PROMO_TILES } from "~/lib/home-demo";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";

/**
 * Promo tile grid after the hero.
 * Demo images/links for now — wire to Accounts API later (see home-demo.ts).
 */
export const PromoTiles = component$(() => {
  const { locale } = useI18n();
  const tiles = HOME_PROMO_TILES;

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
              src={tile.imageUrl}
              alt={tile.label}
              class="home-promo-tiles__img"
              width={800}
              height={600}
              loading="lazy"
            />
            <span class="home-promo-tiles__cta">{tStatic(locale, "home.shopNow")}</span>
          </Link>
        ))}
      </div>
    </section>
  );
});
