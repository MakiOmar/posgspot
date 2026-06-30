import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import type { StoreSettings } from "~/lib/types";

interface SiteFooterProps {
  settings: StoreSettings;
}

export const SiteFooter = component$<SiteFooterProps>(({ settings }) => {
  const year = new Date().getFullYear();

  return (
    <footer class="site-footer">
      <div class="container footer-grid">
        <div>
          <h3>{settings.business_name}</h3>
          <p class="footer-muted">
            Your destination for gaming consoles, accessories, and repair services.
          </p>
          {settings.contact.phone ? (
            <p class="footer-muted">Phone: {settings.contact.phone}</p>
          ) : null}
          {settings.contact.email ? (
            <p class="footer-muted">Email: {settings.contact.email}</p>
          ) : null}
        </div>
        <div>
          <h3>Shop</h3>
          <ul>
            <li>
              <Link href="/products">All products</Link>
            </li>
            <li>
              <Link href="/cart">Cart</Link>
            </li>
          </ul>
        </div>
        <div>
          <h3>Follow us</h3>
          <ul>
            {settings.social.facebook && settings.social.facebook !== "#" ? (
              <li>
                <a href={settings.social.facebook} rel="noopener noreferrer" target="_blank">
                  Facebook
                </a>
              </li>
            ) : null}
            {settings.social.instagram && settings.social.instagram !== "#" ? (
              <li>
                <a href={settings.social.instagram} rel="noopener noreferrer" target="_blank">
                  Instagram
                </a>
              </li>
            ) : null}
          </ul>
        </div>
      </div>
      <div class="container footer-bottom">
        © {year} {settings.business_name}. All rights reserved.
      </div>
    </footer>
  );
});
