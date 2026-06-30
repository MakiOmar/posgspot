import { component$ } from "@builder.io/qwik";
import { Link, useLocation } from "@builder.io/qwik-city";
import { CartIcon, MenuIcon, PhoneIcon, SearchIcon, UserIcon } from "~/components/icons";
import { accountDisplayName, isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { cartSubtotal, totalCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import type { Category, StoreSettings } from "~/lib/types";

interface SiteHeaderProps {
  settings: StoreSettings;
  categories: Category[];
}

export const SiteHeader = component$<SiteHeaderProps>(({ settings, categories }) => {
  const loc = useLocation();
  const cart = useCart();
  const auth = useAuth();
  const signedIn = isAuthenticated(auth);

  const itemCount = totalCartItems(cart);
  const subtotal = cartSubtotal(cart);
  // Build a clean tel: href (digits and leading + only) separate from display text.
  const phone = settings.contact?.phone || "";
  const phoneHref = phone.replace(/[^\d+]/g, "");

  return (
    <header class="site-header">
      {/* Optional promo/announcement strip (admin-controlled). */}
      {settings.announcement.enabled && settings.announcement.message ? (
        <div class="announcement">
          {settings.announcement.link ? (
            <a href={settings.announcement.link}>{settings.announcement.message}</a>
          ) : (
            settings.announcement.message
          )}
        </div>
      ) : null}

      {/* Row 1 — top contact bar. */}
      {phone ? (
        <div class="header-topbar">
          <div class="container header-topbar-inner">
            <a class="topbar-phone" href={`tel:${phoneHref}`}>
              <PhoneIcon size={16} />
              <span>{phone}</span>
            </a>
            {/* Right side reserved for payment/installment logos (future). */}
            <div class="topbar-extra" aria-hidden="true" />
          </div>
        </div>
      ) : null}

      {/* Row 2 — logo, search, actions. */}
      <div class="header-main">
        <div class="container header-main-inner">
          <Link href="/" class="brand" aria-label={settings.business_name}>
            {settings.logo_url ? (
              <img
                src={settings.logo_url}
                alt={settings.business_name}
                width={120}
                height={36}
              />
            ) : (
              <span>{settings.business_name}</span>
            )}
          </Link>

          <form class="header-search" action="/products" method="get" role="search">
            <input
              type="search"
              name="q"
              placeholder="Search for games, consoles, accessories…"
              aria-label="Search products"
              value={loc.url.searchParams.get("q") || ""}
            />
            <button type="submit" aria-label="Search">
              <SearchIcon size={18} />
            </button>
          </form>

          <div class="header-actions">
            {/* Account entry: profile when signed in, otherwise sign in. */}
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

      {/* Row 3 — category navigation. */}
      <nav class="header-catnav" aria-label="Categories">
        <div class="container header-catnav-inner">
          <Link href="/products" class="catnav-viewall">
            <MenuIcon size={18} />
            <span>View All</span>
          </Link>
          <ul class="catnav-links">
            {categories.map((cat) => (
              <li key={cat.id}>
                <Link
                  href={
                    cat.slug
                      ? `/category/${cat.slug}`
                      : `/products?category_id=${cat.id}`
                  }
                >
                  {cat.name}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </nav>
    </header>
  );
});
