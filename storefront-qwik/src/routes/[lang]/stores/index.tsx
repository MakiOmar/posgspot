import { component$, useSignal } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { MapPinIcon, PhoneIcon } from "~/components/icons";
import { ProtectedEmailLink } from "~/components/layout/protected-email-link";
import { JsonLd } from "~/components/seo/json-ld";
import { fetchLocations } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { pickDefaultStore, storeMapEmbedUrl } from "~/lib/maps";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { StoreLocation } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useStoreLocatorLocations = routeLoader$(async () => {
  try {
    const { data } = await fetchLocations();
    return data;
  } catch {
    return [] as StoreLocation[];
  }
});

function storesJsonLd(locations: StoreLocation[], businessName: string) {
  return {
    "@context": "https://schema.org",
    "@type": "ItemList",
    name: `${businessName} stores`,
    numberOfItems: locations.length,
    itemListElement: locations.map((loc, index) => {
      const item: Record<string, unknown> = {
        "@type": "Store",
        name: loc.name,
        address: loc.address || undefined,
        telephone: loc.phone || undefined,
        url: loc.maps_url || undefined,
      };
      if (loc.latitude != null && loc.longitude != null) {
        item.geo = {
          "@type": "GeoCoordinates",
          latitude: loc.latitude,
          longitude: loc.longitude,
        };
      }
      return {
        "@type": "ListItem",
        position: index + 1,
        item,
      };
    }),
  };
}

export default component$(() => {
  const settings = useSiteSettings();
  const locations = useStoreLocatorLocations();
  const { locale } = useI18n();
  const initial = pickDefaultStore(locations.value);
  const selectedId = useSignal<number | null>(initial?.id ?? null);

  const selected =
    locations.value.find((loc) => loc.id === selectedId.value) ?? initial;

  return (
    <article class="content-page stores-page">
      <JsonLd data={storesJsonLd(locations.value, settings.value.business_name)} />

      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "nav.stores")}</span>
      </nav>

      <header class="stores-page__intro">
        <h1 class="content-title">{tStatic(locale, "stores.title")}</h1>
        <p class="stores-page__lead">{tStatic(locale, "stores.lead")}</p>
      </header>

      {locations.value.length === 0 ? (
        <div class="empty-state">
          <p>{tStatic(locale, "stores.empty")}</p>
          <Link href={localePath(locale, "/contact")} class="btn btn-primary">
            {tStatic(locale, "nav.contact")}
          </Link>
        </div>
      ) : (
        <div class="stores-layout">
          <div class="stores-map-wrap">
            <iframe
              title={tStatic(locale, "stores.mapTitle", {
                name: selected?.name || settings.value.business_name,
              })}
              class="stores-map"
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
              src={storeMapEmbedUrl(selected)}
            />
          </div>

          <ul class="stores-list" aria-label={tStatic(locale, "stores.listLabel")}>
            {locations.value.map((loc) => {
              const isSelected = selected?.id === loc.id;
              const phoneHref = loc.phone ? loc.phone.replace(/[^\d+]/g, "") : "";

              return (
                <li key={loc.id} class={isSelected ? "stores-list__li--active" : undefined}>
                  <div class={`stores-list__item${isSelected ? " stores-list__item--active" : ""}`}>
                    <button
                      type="button"
                      class="stores-list__select"
                      aria-pressed={isSelected}
                      onClick$={() => {
                        selectedId.value = loc.id;
                      }}
                    >
                      <span class="stores-list__name">{loc.name}</span>
                      {loc.enable_pickup ? (
                        <span class="stores-list__badge">{tStatic(locale, "stores.pickup")}</span>
                      ) : null}
                      {loc.address ? (
                        <span class="stores-list__meta footer-contact">
                          <MapPinIcon size={16} />
                          <span>{loc.address}</span>
                        </span>
                      ) : null}
                      {loc.phone ? (
                        <span class="stores-list__meta footer-contact">
                          <PhoneIcon size={16} />
                          <span dir="ltr">{loc.phone}</span>
                        </span>
                      ) : null}
                    </button>
                    <div class="stores-list__actions">
                      {loc.maps_url ? (
                        <a
                          href={loc.maps_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          class="stores-list__link"
                        >
                          {tStatic(locale, "stores.getDirections")}
                        </a>
                      ) : null}
                      {loc.phone ? (
                        <a href={`tel:${phoneHref}`} class="stores-list__link" dir="ltr">
                          {tStatic(locale, "stores.call")}
                        </a>
                      ) : null}
                      {loc.email_encoded ? (
                        <ProtectedEmailLink emailEncoded={loc.email_encoded} />
                      ) : null}
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
        </div>
      )}

      <p class="stores-page__footer-note">
        {tStatic(locale, "stores.contactPrompt")}{" "}
        <Link href={localePath(locale, "/contact")}>{tStatic(locale, "nav.contact")}</Link>
      </p>
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const title = tStatic(lang, "stores.seoTitle", { businessName: settings.business_name });
  const description = tStatic(lang, "stores.seoDescription", {
    businessName: settings.business_name,
  });
  const canonical = publicSeoLinks(url.origin, "/stores", lang)[0]?.href;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        ...(canonical ? [{ property: "og:url", content: canonical }] : []),
        { name: "twitter:card", content: "summary" },
      ],
      links: publicSeoLinks(url.origin, "/stores", lang),
    },
    settings,
  );
};
