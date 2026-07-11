import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { ProductCard } from "~/components/catalog/product-card";
import { tStatic, useI18n } from "~/lib/i18n/context";
import {
  getRecentlyViewedForDisplay,
  recordRecentlyViewed,
} from "~/lib/recently-viewed";
import type { ProductSummary, StoreSettings } from "~/lib/types";

interface RecentlyViewedProps {
  settings: StoreSettings;
  /** When set, this product is recorded and excluded from the grid. */
  recordProduct?: ProductSummary | null;
  excludeProductId?: number;
  headingId?: string;
  class?: string;
}

/**
 * Client-only recently viewed grid (localStorage). Renders nothing until hydrated
 * and when the list is empty after exclusions.
 */
export const RecentlyViewed = component$<RecentlyViewedProps>((props) => {
  const { locale } = useI18n();
  const items = useSignal<ProductSummary[]>([]);
  const ready = useSignal(false);

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => props.recordProduct?.id);
    track(() => props.excludeProductId);
    track(() => locale);

    if (props.recordProduct) {
      recordRecentlyViewed(locale, props.recordProduct);
    }

    items.value = getRecentlyViewedForDisplay(locale, {
      excludeProductId: props.excludeProductId ?? props.recordProduct?.id,
    });
    ready.value = true;
  });

  if (!ready.value || items.value.length === 0) {
    return null;
  }

  const headingId = props.headingId || "recently-viewed-heading";

  return (
    <section
      class={`recently-viewed home-section${props.class ? ` ${props.class}` : ""}`}
      aria-labelledby={headingId}
    >
      <div class="home-section__head">
        <h2 id={headingId} class="home-section__title">
          {tStatic(locale, "catalog.recentlyViewed")}
        </h2>
      </div>
      <div class="product-grid">
        {items.value.map((product) => (
          <ProductCard key={product.id} product={product} settings={props.settings} />
        ))}
      </div>
    </section>
  );
});
