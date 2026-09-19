import { $, component$, useOnWindow, useSignal } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import {
  MenuIcon,
  PhoneIcon,
  UserIcon,
} from "~/components/icons";
import { CategoriesDrawer } from "~/components/layout/categories-drawer";
import { HeaderSearch } from "~/components/layout/header-search";
import { HeaderWishlist } from "~/components/layout/header-wishlist";
import { LanguageSwitcher } from "~/components/layout/language-switcher";
import { MiniCart } from "~/components/layout/mini-cart";
import { MobileNavDrawer } from "~/components/layout/mobile-nav-drawer";
import { HeaderNavItems } from "~/components/content/content-blocks";
import { accountDisplayName, isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { HEADER_STYLE } from "~/lib/config";
import {
  closeHeaderDropdown,
  toggleHeaderDropdown,
  useHeaderDropdown,
} from "~/lib/header-dropdown-context";
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
  const headerMenu = useHeaderDropdown();
  const auth = useAuth();
  const signedIn = isAuthenticated(auth);
  const isStyleOne = HEADER_STYLE === "one";
  const scrolled = useSignal(false);
  const navLinks = buildMainNavLinks(locale, {
    digitalEnabled: settings.digital?.enabled !== false,
    categories,
    customBundleEnabled: settings.custom_bundle?.enabled === true,
    sellToUsEnabled: settings.sell_to_us?.enabled === true,
  });
  const categoriesOpen = headerMenu.openId === "categories";
  const mobileNavOpen = headerMenu.openId === "mobileNav";

  const phone = settings.contact?.phone || "";
  const phoneHref = phone.replace(/[^\d+]/g, "");

  useOnWindow(
    "scroll",
    $(() => {
      scrolled.value = window.scrollY > 8;
    }),
  );

  const closeCategories$ = $(() => {
    closeHeaderDropdown(headerMenu, "categories");
  });

  const toggleCategories$ = $(() => {
    toggleHeaderDropdown(headerMenu, "categories");
  });

  const closeMobileNav$ = $(() => {
    closeHeaderDropdown(headerMenu, "mobileNav");
  });

  const toggleMobileNav$ = $(() => {
    toggleHeaderDropdown(headerMenu, "mobileNav");
  });

  return (
    <>
      <header
        class={`site-header site-header--${HEADER_STYLE}${scrolled.value ? " is-scrolled" : ""}`}
      >
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
            {isStyleOne ? (
              <button
                type="button"
                class="header-nav-menu header-nav-menu--icon"
                aria-expanded={mobileNavOpen}
                aria-controls="mobile-nav-panel"
                aria-label={tStatic(locale, "nav.menu")}
                onClick$={toggleMobileNav$}
              >
                <MenuIcon size={22} />
              </button>
            ) : null}

            <Link
              href={localePath(locale, "/")}
              class="brand"
              aria-label={settings.business_name}
              prefetch={false}
            >
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
              <nav class="header-nav header-nav--inline" aria-label={tStatic(locale, "header.mainNav")}>
                <div class="header-nav-links">
                  <HeaderNavItems links={navLinks} linkClass="header-nav-link" />
                </div>
              </nav>
            ) : null}

            <HeaderSearch settings={settings} variant={isStyleOne ? "modal" : "inline"} />

            <div class="header-actions">
              <LanguageSwitcher settings={settings} />

              <HeaderWishlist />

              {!isStyleOne && phone ? (
                <a class="header-phone" href={`tel:${phoneHref}`} dir="ltr">
                  <PhoneIcon size={18} />
                  <span class="header-phone-text">{phone}</span>
                </a>
              ) : null}

              <Link
                href={localePath(locale, signedIn ? "/account" : "/login")}
                class="action-link"
                prefetch={false}
                aria-label={signedIn ? tStatic(locale, "header.myAccount") : tStatic(locale, "header.signIn")}
              >
                {signedIn && auth.contact?.avatar_url ? (
                  <img
                    class="header-account-avatar"
                    src={auth.contact.avatar_url}
                    alt=""
                    width={22}
                    height={22}
                  />
                ) : (
                  <UserIcon size={22} />
                )}
                {!isStyleOne ? (
                  <span class="action-text">
                    {signedIn ? accountDisplayName(auth) : tStatic(locale, "header.signIn")}
                  </span>
                ) : null}
              </Link>

              <MiniCart settings={settings} />
            </div>
          </div>
        </div>

        {!isStyleOne ? (
          <nav class="header-subnav" aria-label={tStatic(locale, "header.siteNav")}>
            <div class="container header-subnav-inner">
              <button
                type="button"
                class="header-nav-categories"
                aria-expanded={categoriesOpen}
                aria-controls="categories-panel"
                onClick$={toggleCategories$}
              >
                <MenuIcon size={18} />
                <span>{tStatic(locale, "nav.categories")}</span>
              </button>
              <button
                type="button"
                class="header-nav-menu"
                aria-expanded={mobileNavOpen}
                aria-controls="mobile-nav-panel"
                onClick$={toggleMobileNav$}
              >
                <MenuIcon size={18} />
                <span>{tStatic(locale, "nav.menu")}</span>
              </button>
              <div class="header-nav-links">
                <HeaderNavItems links={navLinks} linkClass="header-subnav-link" />
              </div>
            </div>
          </nav>
        ) : null}
      </header>

      {!isStyleOne ? (
        <CategoriesDrawer
          categories={categories}
          open={categoriesOpen}
          onClose$={closeCategories$}
        />
      ) : null}

      <MobileNavDrawer
        links={navLinks}
        open={mobileNavOpen}
        onClose$={closeMobileNav$}
      />
    </>
  );
});
