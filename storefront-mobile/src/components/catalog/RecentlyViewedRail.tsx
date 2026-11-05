import { useCallback, useState } from "react";
import { useFocusEffect } from "expo-router";
import { useApp } from "../../contexts/AppContext";
import { getRecentlyViewedForDisplay, recordRecentlyViewed } from "../../lib/recently-viewed-storage";
import type { ProductSummary } from "../../lib/types";
import { ProductRail } from "../home/home-rails";

/**
 * Recently viewed rail (AsyncStorage, per locale). Records `recordProduct` first and excludes it;
 * refreshes when the screen regains focus. Renders nothing when the history is empty.
 */
export function RecentlyViewedRail({ recordProduct }: { recordProduct?: ProductSummary | null }) {
  const { t, locale } = useApp();
  const [items, setItems] = useState<ProductSummary[]>([]);

  useFocusEffect(
    useCallback(() => {
      let cancelled = false;
      void (async () => {
        if (recordProduct) {
          await recordRecentlyViewed(locale, recordProduct);
        }
        const next = await getRecentlyViewedForDisplay(locale, {
          excludeProductId: recordProduct?.id,
        });
        if (!cancelled) {
          setItems(next);
        }
      })();
      return () => {
        cancelled = true;
      };
    }, [locale, recordProduct]),
  );

  return <ProductRail title={t("catalog.recentlyViewed")} products={items} />;
}
