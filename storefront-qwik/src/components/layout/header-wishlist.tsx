import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { HeartIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { wishlistCount } from "~/lib/wishlist-actions";
import { useWishlist } from "~/lib/wishlist-context";

export const HeaderWishlist = component$(() => {
  const { locale } = useI18n();
  const wishlist = useWishlist();
  const count = wishlistCount(wishlist);

  return (
    <Link
      href={localePath(locale, "/wishlist")}
      class="action-link action-wishlist"
      aria-label={tStatic(locale, "header.wishlist")}
    >
      <span class="action-wishlist-icon">
        <HeartIcon size={22} />
        {count > 0 ? <span class="cart-badge">{count}</span> : null}
      </span>
      <span class="action-text">{tStatic(locale, "header.wishlist")}</span>
    </Link>
  );
});
