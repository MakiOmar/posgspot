import { $, component$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import { CartIcon, HeartIcon, HomeIcon, SearchIcon, UserIcon } from "~/components/icons";
import { isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { totalCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { HEADER_STYLE } from "~/lib/config";
import { useHeaderDropdown } from "~/lib/header-dropdown-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath, stripLocalePrefix } from "~/lib/i18n/paths";
import { wishlistCount } from "~/lib/wishlist-actions";
import { useWishlist } from "~/lib/wishlist-context";

function isActivePath(barePath: string, target: string): boolean {
  if (target === "/") {
    return barePath === "/" || barePath === "";
  }
  return barePath === target || barePath.startsWith(`${target}/`);
}

/**
 * Fixed app-style bottom bar for mobile/tablet (&lt; 1024px).
 */
export const MobileBottomNav = component$(() => {
  const { locale } = useI18n();
  const loc = useLocation();
  const nav = useNavigate();
  const auth = useAuth();
  const cart = useCart();
  const wishlist = useWishlist();
  const headerMenu = useHeaderDropdown();
  const signedIn = isAuthenticated(auth);
  const bare = stripLocalePrefix(loc.url.pathname);
  const cartCount = totalCartItems(cart);
  const wishCount = wishlistCount(wishlist);
  const isStyleOne = HEADER_STYLE === "one";

  const homeActive = isActivePath(bare, "/");
  const searchActive = isActivePath(bare, "/search") || headerMenu.searchModalOpen;
  const cartActive = isActivePath(bare, "/cart") || isActivePath(bare, "/checkout");
  const wishlistActive = isActivePath(bare, "/wishlist");
  const profileActive =
    isActivePath(bare, "/account") ||
    isActivePath(bare, "/login") ||
    isActivePath(bare, "/register");

  const openSearch$ = $(() => {
    if (isStyleOne) {
      headerMenu.searchModalOpen = true;
      return;
    }
    void nav(localePath(locale, "/search"));
  });

  return (
    <nav class="mobile-bottom-nav" aria-label={tStatic(locale, "header.mobileBar")}>
      <Link
        href={localePath(locale, "/")}
        class={`mobile-bottom-nav__item${homeActive ? " mobile-bottom-nav__item--active" : ""}`}
        aria-current={homeActive ? "page" : undefined}
      >
        <span class="mobile-bottom-nav__icon">
          <HomeIcon size={22} />
        </span>
        <span class="mobile-bottom-nav__label">{tStatic(locale, "nav.home")}</span>
      </Link>

      <button
        type="button"
        class={`mobile-bottom-nav__item${searchActive ? " mobile-bottom-nav__item--active" : ""}`}
        aria-label={tStatic(locale, "header.search")}
        aria-expanded={isStyleOne ? headerMenu.searchModalOpen : undefined}
        onClick$={openSearch$}
      >
        <span class="mobile-bottom-nav__icon">
          <SearchIcon size={22} />
        </span>
        <span class="mobile-bottom-nav__label">{tStatic(locale, "header.search")}</span>
      </button>

      <Link
        href={localePath(locale, "/cart")}
        class={`mobile-bottom-nav__item${cartActive ? " mobile-bottom-nav__item--active" : ""}`}
        aria-current={cartActive ? "page" : undefined}
      >
        <span class="mobile-bottom-nav__icon">
          <CartIcon size={22} />
          {cartCount > 0 ? (
            <span class="cart-badge mobile-bottom-nav__badge">{cartCount}</span>
          ) : null}
        </span>
        <span class="mobile-bottom-nav__label">{tStatic(locale, "header.cart")}</span>
      </Link>

      <Link
        href={localePath(locale, "/wishlist")}
        class={`mobile-bottom-nav__item${wishlistActive ? " mobile-bottom-nav__item--active" : ""}`}
        aria-current={wishlistActive ? "page" : undefined}
      >
        <span class="mobile-bottom-nav__icon">
          <HeartIcon size={22} />
          {wishCount > 0 ? (
            <span class="cart-badge mobile-bottom-nav__badge">{wishCount}</span>
          ) : null}
        </span>
        <span class="mobile-bottom-nav__label">{tStatic(locale, "header.wishlist")}</span>
      </Link>

      <Link
        href={localePath(locale, signedIn ? "/account" : "/login")}
        class={`mobile-bottom-nav__item${profileActive ? " mobile-bottom-nav__item--active" : ""}`}
        aria-current={profileActive ? "page" : undefined}
      >
        <span class="mobile-bottom-nav__icon">
          {signedIn && auth.contact?.avatar_url ? (
            <img
              class="mobile-bottom-nav__avatar"
              src={auth.contact.avatar_url}
              alt=""
              width={22}
              height={22}
            />
          ) : (
            <UserIcon size={22} />
          )}
        </span>
        <span class="mobile-bottom-nav__label">
          {signedIn
            ? tStatic(locale, "header.myAccount")
            : tStatic(locale, "header.signIn")}
        </span>
      </Link>
    </nav>
  );
});
