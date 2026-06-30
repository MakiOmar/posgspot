import { component$, type QRL } from "@builder.io/qwik";
import { CheckIcon, CloseIcon, CrossIcon, MapPinIcon } from "~/components/icons";
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
    if (!open) {
      return null;
    }

    return (
      <div class="modal-backdrop" role="presentation">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="availability-title">
          <button type="button" class="modal-close" aria-label="Close" onClick$={onClose$}>
            <CloseIcon size={20} />
          </button>
          <h2 id="availability-title">Store availability</h2>

          {loading ? <p>Loading availability…</p> : null}
          {error ? <p class="alert alert-error">{error}</p> : null}

          {availability ? (
            <>
              <p class="footer-muted">
                {availability.product_name} — {availability.variation_name}
              </p>
              <p class="footer-muted">
                Available at {availability.in_stock_count} of {availability.locations.length}{" "}
                stores
                {availability.cod_available ? " · COD available" : ""}
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
                        <div class="footer-muted">Qty: {location.qty_available}</div>
                      ) : (
                        <div class="footer-muted">Out of stock</div>
                      )}
                      {location.maps_url ? (
                        <a
                          href={location.maps_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          class="footer-contact"
                        >
                          <MapPinIcon size={14} />
                          View on map
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
    );
  },
);
