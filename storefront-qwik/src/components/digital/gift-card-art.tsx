import { component$ } from "@builder.io/qwik";
import type { DigitalCardArt } from "~/lib/types";

interface GiftCardArtProps {
  art: DigitalCardArt;
}

/**
 * Generated gift card artwork for categories without a poster.
 * Markup mirrors the Accounts `<x-card-art>` Blade component; styles live in `global.css` (`.gift-card-art*`).
 */
export const GiftCardArt = component$<GiftCardArtProps>(({ art }) => {
  const country = art.country_code ? ` (${art.country_code.toUpperCase()})` : "";

  return (
    <div class="gift-card-art">
      <div
        class="gift-card-art__card"
        style={`--gift-card-art-color:${art.color}`}
        role="img"
        aria-label={`${art.brand_label} gift card ${art.value}${country}`}
      >
        {/* White band: gift card title, face value and flag */}
        <div class="gift-card-art__top">
          <div class="gift-card-art__kicker">
            <span lang="ar" dir="rtl">
              بطاقة هدايا
            </span>
            <span>GIFT CARD</span>
          </div>
          <div class="gift-card-art__value-wrap">
            <span class="gift-card-art__value">{art.value}</span>
            {art.flag_url ? (
              <img class="gift-card-art__flag" src={art.flag_url} alt="" width={40} height={30} loading="lazy" />
            ) : null}
          </div>
        </div>
        {/* Brand area: logo (when bundled), brand name and code pill */}
        <div class="gift-card-art__body">
          {art.logo_url ? (
            <img class="gift-card-art__logo" src={art.logo_url} alt="" width={80} height={80} loading="lazy" />
          ) : null}
          <span class="gift-card-art__brand">{art.brand_label}</span>
          <span class="gift-card-art__pill">DIGITAL CODE</span>
        </div>
      </div>
    </div>
  );
});
