import { $, component$, useSignal } from "@builder.io/qwik";
import { HeartIcon } from "~/components/icons";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import type { ProductSummary } from "~/lib/types";
import { isInWishlist, toggleWishlistItem } from "~/lib/wishlist-actions";
import { useWishlist } from "~/lib/wishlist-context";

interface WishlistToggleProps {
  product: ProductSummary;
  /** Overlay on product card media vs inline on PDP. */
  variant?: "overlay" | "inline";
  class?: string;
}

export const WishlistToggle = component$<WishlistToggleProps>(
  ({ product, variant = "overlay", class: className = "" }) => {
    const wishlist = useWishlist();
    const auth = useAuth();
    const { locale } = useI18n();
    const busy = useSignal(false);
    const saved = isInWishlist(wishlist, product.id);
    const label = saved
      ? tStatic(locale, "wishlist.remove")
      : tStatic(locale, "wishlist.add");

    const toggle$ = $(async (event: Event) => {
      event.stopPropagation();
      if (busy.value) {
        return;
      }
      busy.value = true;
      try {
        await toggleWishlistItem(wishlist, auth, product, locale);
      } finally {
        busy.value = false;
      }
    });

    return (
      <button
        type="button"
        class={`wishlist-toggle wishlist-toggle--${variant}${saved ? " wishlist-toggle--active" : ""}${className ? ` ${className}` : ""}`}
        aria-pressed={saved}
        aria-label={label}
        title={label}
        disabled={busy.value}
        preventdefault:click
        onClick$={toggle$}
      >
        <HeartIcon size={variant === "overlay" ? 20 : 22} />
      </button>
    );
  },
);
