import type { StoreLocaleCode } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastAddedToCart } from "~/lib/notify";

/**
 * Confirms an add-to-cart with a toast offering "View cart". Not awaited by callers so the
 * add button frees up immediately instead of waiting for the toast to close.
 */
export function notifyAddedToCart(
  locale: StoreLocaleCode,
  itemName: string,
  navigate: (path: string) => Promise<void>,
): void {
  void toastAddedToCart({
    title: tStatic(locale, "cart.addedToCart"),
    itemName,
    viewCartLabel: tStatic(locale, "miniCart.viewCart"),
  })
    .then((viewCart) => (viewCart ? navigate(localePath(locale, "/cart")) : undefined))
    .catch(() => undefined);
}
