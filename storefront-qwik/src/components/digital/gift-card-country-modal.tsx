import { component$, useSignal, useVisibleTask$, type QRL } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { CloseIcon } from "~/components/icons";
import type { StoreLocaleCode } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { giftCardBrowseQuery, type GiftCardBrandGroup } from "~/lib/gift-card-groups";
import { brandDisplayLabel, countryDisplayLabel } from "~/lib/gift-card-labels";

interface GiftCardCountryModalProps {
  /** Brand whose countries are listed; null keeps the dialog closed. */
  group: GiftCardBrandGroup | null;
  lang: StoreLocaleCode;
  basePath: string;
  onClose$: QRL<() => void>;
}

export const GiftCardCountryModal = component$<GiftCardCountryModalProps>(({ group, lang, basePath, onClose$ }) => {
  const portalHost = useSignal<HTMLDivElement>();

  // Render the overlay on document.body so page layout cannot clip it.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    const host = portalHost.value;
    if (!host) {
      return;
    }
    document.body.appendChild(host);
    cleanup(() => host.remove());
  });

  const brandLabel = group ? brandDisplayLabel(lang, group.brand, group.label) : "";

  return (
    <div ref={portalHost}>
      {group ? (
        <div
          class="modal-backdrop"
          role="presentation"
          onClick$={(event, element) => {
            if (event.target === element) {
              onClose$();
            }
          }}
        >
          <div class="modal gift-card-country" role="dialog" aria-modal="true" aria-labelledby="gift-card-country-title">
            <button type="button" class="modal-close" aria-label={tStatic(lang, "a11y.close")} onClick$={onClose$}>
              <CloseIcon size={20} />
            </button>
            <h2 id="gift-card-country-title">{tStatic(lang, "digital.chooseCountryFor", { brand: brandLabel })}</h2>

            {/* One link per country: flag, name, card count */}
            <ul class="gift-card-country__list">
              {group.countries.map((country) => (
                <li key={country.key}>
                  <Link
                    class="gift-card-country__option"
                    href={`${basePath}${giftCardBrowseQuery(group.brand, country.key)}`}
                    onClick$={onClose$}
                  >
                    {country.flag_url ? (
                      <img class="gift-card-country__flag" src={country.flag_url} alt="" width={28} height={20} loading="lazy" />
                    ) : (
                      <span class="gift-card-country__flag gift-card-country__flag--blank" aria-hidden="true" />
                    )}
                    <span class="gift-card-country__label">{countryDisplayLabel(lang, country.key, country.label)}</span>
                    <span class="gift-card-country__count">{country.count}</span>
                  </Link>
                </li>
              ))}
            </ul>

            <Link class="btn btn-secondary gift-card-country__all" href={`${basePath}${giftCardBrowseQuery(group.brand)}`} onClick$={onClose$}>
              {tStatic(lang, "digital.allBrandCards", { brand: brandLabel })}
            </Link>
          </div>
        </div>
      ) : null}
    </div>
  );
});
