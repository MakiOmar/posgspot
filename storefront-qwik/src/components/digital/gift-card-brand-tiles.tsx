import { component$, type QRL } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import type { StoreLocaleCode } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { giftCardBrowseQuery, type GiftCardBrandGroup } from "~/lib/gift-card-groups";
import { brandDisplayLabel } from "~/lib/gift-card-labels";

interface GiftCardBrandTilesProps {
  groups: GiftCardBrandGroup[];
  lang: StoreLocaleCode;
  /** Localized `/gift-cards` path the tiles link to. */
  basePath: string;
  onChoose$: QRL<(brand: string) => void>;
}

/** Brand tiles; a brand sold in one country links straight to its cards, others open the country picker. */
export const GiftCardBrandTiles = component$<GiftCardBrandTilesProps>(({ groups, lang, basePath, onChoose$ }) => {
  return (
    <ul class="gift-card-brands">
      {groups.map((group) => {
        const label = brandDisplayLabel(lang, group.brand, group.label);
        const body = (
          <>
            {/* Logo on the brand colour, or the first letter when there is no logo */}
            <span class="gift-card-brands__logo" style={{ backgroundColor: group.color }}>
              {group.logo_url ? (
                <img src={group.logo_url} alt="" width={96} height={54} loading="lazy" />
              ) : (
                <span class="gift-card-brands__initial" aria-hidden="true">
                  {label.slice(0, 1)}
                </span>
              )}
            </span>
            <span class="gift-card-brands__label">{label}</span>
            <span class="gift-card-brands__count">
              {tStatic(lang, "digital.brandCardsCount", { count: group.count })}
            </span>
          </>
        );

        return (
          <li key={group.brand}>
            {group.countries.length === 1 ? (
              <Link class="gift-card-brands__tile" href={`${basePath}${giftCardBrowseQuery(group.brand, group.countries[0].key)}`}>
                {body}
              </Link>
            ) : (
              <button type="button" class="gift-card-brands__tile" onClick$={() => onChoose$(group.brand)}>
                {body}
              </button>
            )}
          </li>
        );
      })}
    </ul>
  );
});
