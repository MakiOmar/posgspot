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
import { LanguageSwitcher } from "~/components/layout/language-switcher";
import { HeaderNavItems } from "~/components/content/content-blocks";
import { accountDisplayName, isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { cartSubtotal, totalCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { HEADER_STYLE } from "~/lib/config";
import { formatPrice } from "~/lib/format";
import { buildMainNavLinks } from "~/lib/header-nav";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { Category, StoreSettings } from "~/lib/types";

interface SiteHeaderProps {
  settings: StoreSettings;
  categories: Category[];
}

export const SiteHeader = component$<SiteHeaderProps>(({ settings, categories }) => {
  const { locale } = useI18n();
  const categoriesOpen = useSignal(false);
  const cart = useCart();
  const auth = useAuth();
  const signedIn = isAuthenticated(auth);
  const isStyleOne = HEADER_STYLE === "one";
  const navLinks = buildMainNavLinks(locale);

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
            <Link href={localePath(locale, "/")} class="brand" aria-label={settings.business_name}>
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
                  <span>{tStatic(locale, "nav.categories")}</span>
                </button>
                <HeaderNavItems links={navLinks} linkClass="header-nav-link" />
              </nav>
            ) : null}

            <HeaderSearch settings={settings} />

            <div class="header-actions">
              <LanguageSwitcher settings={settings} />

              {phone ? (
                <a class="header-phone" href={`tel:${phoneHref}`} dir="ltr">
                  <PhoneIcon size={18} />
                  <span class="header-phone-text">{phone}</span>
                </a>
              ) : null}

              <Link
                href={localePath(locale, signedIn ? "/account" : "/login")}
                class="action-link"
                aria-label={signedIn ? tStatic(locale, "header.myAccount") : tStatic(locale, "header.signIn")}
              >
                <UserIcon size={22} />
                <span class="action-text">
                  {signedIn ? accountDisplayName(auth) : tStatic(locale, "header.signIn")}
                </span>
              </Link>

              <Link href={localePath(locale, "/cart")} class="action-link action-cart" aria-label={tStatic(locale, "header.cart")}>
                <span class="action-cart-icon">
                  <CartIcon size={22} />
                  {itemCount > 0 ? <span class="cart-badge">{itemCount}</span> : null}
                </span>
                <span class="action-text">
                  {formatPrice(subtotal, settings.currency, locale)}
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
                <span>{tStatic(locale, "nav.categories")}</span>
              </button>
              <HeaderNavItems links={navLinks} linkClass="header-subnav-link" />
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
