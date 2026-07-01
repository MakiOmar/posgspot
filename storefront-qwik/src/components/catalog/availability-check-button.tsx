import { $, component$, useSignal } from "@builder.io/qwik";
import { AvailabilityModal } from "~/components/catalog/availability-modal";
import { fetchAvailability } from "~/lib/api";
import type { ProductAvailability } from "~/lib/types";

interface AvailabilityCheckButtonProps {
  productId: number;
  variationId: number;
  class?: string;
  block?: boolean;
}

/** Opens the per-location stock modal (same as PDP). */
export const AvailabilityCheckButton = component$<AvailabilityCheckButtonProps>(
  ({ productId, variationId, class: className, block }) => {
    const modalOpen = useSignal(false);
    const modalLoading = useSignal(false);
    const modalError = useSignal<string | null>(null);
    const availability = useSignal<ProductAvailability | null>(null);

    const openAvailability$ = $(async () => {
      modalOpen.value = true;
      modalLoading.value = true;
      modalError.value = null;
      availability.value = null;

      try {
        const { data } = await fetchAvailability(productId, variationId);
        availability.value = data;
      } catch {
        modalError.value = "Could not load store availability.";
      } finally {
        modalLoading.value = false;
      }
    });

    const closeModal$ = $(() => {
      modalOpen.value = false;
    });

    const classes = [
      "btn",
      "btn-secondary",
      block ? "btn-block" : "",
      className ?? "",
    ]
      .filter(Boolean)
      .join(" ");

    return (
      <>
        <button type="button" class={classes} onClick$={openAvailability$}>
          Check store availability
        </button>
        <AvailabilityModal
          open={modalOpen.value}
          loading={modalLoading.value}
          error={modalError.value}
          availability={availability.value}
          onClose$={closeModal$}
        />
      </>
    );
  },
);
