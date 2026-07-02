import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import {
  FacebookIcon,
  InstagramIcon,
  PhoneIcon,
  TiktokIcon,
  YoutubeIcon,
} from "~/components/icons";
import { ProtectedEmailLink } from "~/components/layout/protected-email-link";
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
            <p class="footer-muted footer-contact">
              <PhoneIcon size={16} />
              <a href={`tel:${settings.contact.phone}`}>{settings.contact.phone}</a>
            </p>
          ) : null}
          {settings.contact.email_encoded ? (
            <ProtectedEmailLink emailEncoded={settings.contact.email_encoded} />
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
          <h3>Policies</h3>
          <ul>
            <li>
              <Link href="/terms-and-conditions">Terms &amp; Conditions</Link>
            </li>
            <li>
              <Link href="/privacy-policy">Privacy Policy</Link>
            </li>
            <li>
              <Link href="/return-policy">Return &amp; Exchange</Link>
            </li>
          </ul>
        </div>
        <div>
          <h3>Follow us</h3>
          <div class="footer-social">
            {settings.social.facebook && settings.social.facebook !== "#" ? (
              <a
                href={settings.social.facebook}
                rel="noopener noreferrer"
                target="_blank"
                aria-label="Facebook"
              >
                <FacebookIcon size={22} />
              </a>
            ) : null}
            {settings.social.instagram && settings.social.instagram !== "#" ? (
              <a
                href={settings.social.instagram}
                rel="noopener noreferrer"
                target="_blank"
                aria-label="Instagram"
              >
                <InstagramIcon size={22} />
              </a>
            ) : null}
            {settings.social.tiktok && settings.social.tiktok !== "#" ? (
              <a
                href={settings.social.tiktok}
                rel="noopener noreferrer"
                target="_blank"
                aria-label="TikTok"
              >
                <TiktokIcon size={22} />
              </a>
            ) : null}
            {settings.social.youtube && settings.social.youtube !== "#" ? (
              <a
                href={settings.social.youtube}
                rel="noopener noreferrer"
                target="_blank"
                aria-label="YouTube"
              >
                <YoutubeIcon size={22} />
              </a>
            ) : null}
          </div>
        </div>
      </div>
      <div class="container footer-bottom">
        © {year} {settings.business_name}. All rights reserved.
      </div>
    </footer>
  );
});
