import { component$, useSignal, useVisibleTask$, type QRL } from "@builder.io/qwik";
import { CheckIcon, CloseIcon, CrossIcon, MapPinIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";
import type { ProductAvailability } from "~/lib/types";

interface AvailabilityModalProps {
  open: boolean;
  loading: boolean;
  error: string | null;
  availability: ProductAvailability | null;
  onClose$: QRL<() => void>;
}

export const AvailabilityModal = component$<AvailabilityModalProps>(
  ({ open, loading, error, availability, onClose$ }) => {
    const portalHost = useSignal<HTMLDivElement>();
    const { locale } = useI18n();

    // Render the overlay on document.body so card overflow/transform cannot clip it.
    // eslint-disable-next-line qwik/no-use-visible-task
    useVisibleTask$(({ cleanup }) => {
      const host = portalHost.value;
      if (!host) {
        return;
      }

      document.body.appendChild(host);
      cleanup(() => {
        host.remove();
      });
    });

    return (
      <div ref={portalHost}>
        {open ? (
          <div class="modal-backdrop" role="presentation">
            <div class="modal" role="dialog" aria-modal="true" aria-labelledby="availability-title">
              <button
                type="button"
                class="modal-close"
                aria-label={tStatic(locale, "a11y.close")}
                onClick$={onClose$}
              >
                <CloseIcon size={20} />
              </button>
              <h2 id="availability-title">{tStatic(locale, "availability.title")}</h2>

              {loading ? <p>{tStatic(locale, "availability.loading")}</p> : null}
              {error ? <p class="alert alert-error">{error}</p> : null}

              {availability ? (
                <>
                  <p class="footer-muted">
                    {availability.product_name} — {availability.variation_name}
                  </p>
                  <p class="footer-muted">
                    {tStatic(locale, "availability.availableAt", {
                      inStock: availability.in_stock_count,
                      total: availability.locations.length,
                    })}
                    {availability.cod_available ? tStatic(locale, "availability.codAvailable") : ""}
                  </p>
                  <ul style={{ listStyle: "none", margin: "0", padding: "0" }}>
                    {availability.locations.map((location) => (
                      <li key={location.location_id} class="availability-row">
                        <span
                          class={`availability-icon ${location.in_stock ? "availability-icon--in" : "availability-icon--out"}`}
                          aria-hidden="true"
                        >
                          {location.in_stock ? (
                            <CheckIcon size={18} />
                          ) : (
                            <CrossIcon size={18} />
                          )}
                        </span>
                        <div>
                          <strong>{location.name}</strong>
                          <div class="footer-muted">{location.address}</div>
                          {location.in_stock ? (
                            <div class="footer-muted">
                              {tStatic(locale, "availability.qtyAvailable", {
                                qty: location.qty_available,
                              })}
                            </div>
                          ) : (
                            <div class="footer-muted">{tStatic(locale, "catalog.outOfStock")}</div>
                          )}
                          {location.maps_url ? (
                            <a
                              href={location.maps_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              class="footer-contact"
                            >
                              <MapPinIcon size={14} />
                              {tStatic(locale, "availability.viewOnMap")}
                            </a>
                          ) : null}
                        </div>
                      </li>
                    ))}
                  </ul>
                </>
              ) : null}
            </div>
          </div>
        ) : null}
      </div>
    );
  },
);
