import { component$ } from "@builder.io/qwik";
import { Link, useLocation } from "@builder.io/qwik-city";
import { totalCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import type { StoreSettings } from "~/lib/types";

interface SiteHeaderProps {
  settings: StoreSettings;
}

export const SiteHeader = component$<SiteHeaderProps>(({ settings }) => {
  const loc = useLocation();
  const cart = useCart();

  return (
    <header class="site-header">
      {settings.announcement.enabled && settings.announcement.message ? (
        <div class="announcement">
          {settings.announcement.link ? (
            <a href={settings.announcement.link}>{settings.announcement.message}</a>
          ) : (
            settings.announcement.message
          )}
        </div>
      ) : null}
      <div class="container header-inner">
        <Link href="/" class="brand">
          {settings.logo_url ? (
            <img src={settings.logo_url} alt={settings.business_name} width={120} height={36} />
          ) : null}
          <span>{settings.business_name}</span>
        </Link>

        <nav class="header-nav" aria-label="Main">
          <Link href="/">Home</Link>
          <Link href="/products">Shop</Link>
        </nav>

        <form class="header-search" action="/products" method="get">
          <input
            type="search"
            name="q"
            placeholder="Search products…"
            aria-label="Search products"
            value={loc.url.searchParams.get("q") || ""}
          />
          <button type="submit">Search</button>
        </form>

        <div class="header-actions">
          <Link href="/cart" class="icon-link">
            Cart
            {totalCartItems(cart) > 0 ? (
              <span class="cart-badge">{totalCartItems(cart)}</span>
            ) : null}
          </Link>
        </div>
      </div>
    </header>
  );
});
