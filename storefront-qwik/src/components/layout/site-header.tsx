import { $, component$, useSignal } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import {
  CartIcon,
  MenuIcon,
  PhoneIcon,
  UserIcon,
} from "~/components/icons";
import { CategoriesDrawer } from "~/components/layout/categories-drawer";
import { HeaderSearch } from "~/components/layout/header-search";
import { HeaderNavItems } from "~/components/content/content-blocks";
import { accountDisplayName, isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { cartSubtotal, totalCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { HEADER_STYLE } from "~/lib/config";
import { MAIN_NAV_LINKS } from "~/lib/header-nav";
import { formatPrice } from "~/lib/format";
import type { Category, StoreSettings } from "~/lib/types";

interface SiteHeaderProps {
  settings: StoreSettings;
  categories: Category[];
}

export const SiteHeader = component$<SiteHeaderProps>(({ settings, categories }) => {
  const categoriesOpen = useSignal(false);
  const cart = useCart();
  const auth = useAuth();
  const signedIn = isAuthenticated(auth);
  const isStyleOne = HEADER_STYLE === "one";

  const itemCount = totalCartItems(cart);
  const subtotal = cartSubtotal(cart);
  const phone = settings.contact?.phone || "";
  const phoneHref = phone.replace(/[^\d+]/g, "");

  const closeCategories$ = $(() => {
    categoriesOpen.value = false;
  });

  const toggleCategories$ = $(() => {
    categoriesOpen.value = !categoriesOpen.value;
  });

  return (
    <>
      <header class={`site-header site-header--${HEADER_STYLE}`}>
        {settings.announcement.enabled && settings.announcement.message ? (
          <div class="announcement">
            {settings.announcement.link ? (
              <a href={settings.announcement.link}>{settings.announcement.message}</a>
            ) : (
              settings.announcement.message
            )}
          </div>
        ) : null}

        <div class="header-main">
          <div class="container header-main-inner">
            <Link href="/" class="brand" aria-label={settings.business_name}>
              {settings.logo_url ? (
                <img
                  src={settings.logo_url}
                  alt={settings.business_name}
                  width={283}
                  height={85}
                />
              ) : (
                <span>{settings.business_name}</span>
              )}
            </Link>

            {isStyleOne ? (
              <nav class="header-nav" aria-label="Main">
                <button
                  type="button"
                  class="header-nav-categories"
                  aria-expanded={categoriesOpen.value}
                  aria-controls="categories-panel"
                  onClick$={toggleCategories$}
                >
                  <MenuIcon size={18} />
                  <span>Categories</span>
                </button>
                <HeaderNavItems links={MAIN_NAV_LINKS} linkClass="header-nav-link" />
              </nav>
            ) : null}

            <HeaderSearch settings={settings} />

            <div class="header-actions">
              {phone ? (
                <a class="header-phone" href={`tel:${phoneHref}`}>
                  <PhoneIcon size={18} />
                  <span class="header-phone-text">{phone}</span>
                </a>
              ) : null}

              <Link
                href={signedIn ? "/account" : "/login"}
                class="action-link"
                aria-label={signedIn ? "My account" : "Sign in"}
              >
                <UserIcon size={22} />
                <span class="action-text">
                  {signedIn ? accountDisplayName(auth) : "Sign in"}
                </span>
              </Link>

              <Link href="/cart" class="action-link action-cart" aria-label="Cart">
                <span class="action-cart-icon">
                  <CartIcon size={22} />
                  {itemCount > 0 ? <span class="cart-badge">{itemCount}</span> : null}
                </span>
                <span class="action-text">
                  {formatPrice(subtotal, settings.currency)}
                </span>
              </Link>
            </div>
          </div>
        </div>

        {!isStyleOne ? (
          <nav class="header-subnav" aria-label="Site navigation">
            <div class="container header-subnav-inner">
              <button
                type="button"
                class="header-nav-categories"
                aria-expanded={categoriesOpen.value}
                aria-controls="categories-panel"
                onClick$={toggleCategories$}
              >
                <MenuIcon size={18} />
                <span>Categories</span>
              </button>
              <HeaderNavItems links={MAIN_NAV_LINKS} linkClass="header-subnav-link" />
            </div>
          </nav>
        ) : null}
      </header>

      <CategoriesDrawer
        categories={categories}
        open={categoriesOpen}
        onClose$={closeCategories$}
      />
    </>
  );
});
