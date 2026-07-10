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
import { FooterNewsletter } from "~/components/layout/footer-newsletter";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { StoreSettings } from "~/lib/types";

interface SiteFooterProps {
  settings: StoreSettings;
}

export const SiteFooter = component$<SiteFooterProps>(({ settings }) => {
  const year = new Date().getFullYear();
  const { locale } = useI18n();

  return (
    <footer class="site-footer">
      <div class="container footer-grid">
        <div>
          <h3>{settings.business_name}</h3>
          <p class="footer-muted">{tStatic(locale, "footer.tagline")}</p>
          {settings.contact.phone ? (
            <p class="footer-muted footer-contact">
              <PhoneIcon size={16} />
              <a href={`tel:${settings.contact.phone}`} dir="ltr">
                {settings.contact.phone}
              </a>
            </p>
          ) : null}
          {settings.contact.email_encoded ? (
            <ProtectedEmailLink emailEncoded={settings.contact.email_encoded} />
          ) : null}
        </div>
        <div>
          <h3>{tStatic(locale, "footer.shop")}</h3>
          <ul>
            <li>
              <Link href={localePath(locale, "/products")}>{tStatic(locale, "footer.allProducts")}</Link>
            </li>
            <li>
              <Link href={localePath(locale, "/cart")}>{tStatic(locale, "header.cart")}</Link>
            </li>
          </ul>
        </div>
        <div>
          <h3>{tStatic(locale, "footer.policies")}</h3>
          <ul>
            <li>
              <Link href={localePath(locale, "/terms-and-conditions")}>{tStatic(locale, "footer.terms")}</Link>
            </li>
            <li>
              <Link href={localePath(locale, "/privacy-policy")}>{tStatic(locale, "footer.privacy")}</Link>
            </li>
            <li>
              <Link href={localePath(locale, "/return-policy")}>{tStatic(locale, "footer.returns")}</Link>
            </li>
          </ul>
        </div>
        <div>
          <h3>{tStatic(locale, "footer.followUs")}</h3>
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
          <FooterNewsletter settings={settings} />
        </div>
      </div>
      <div class="container footer-bottom">
        <div class="footer-bottom__copy">
          {tStatic(locale, "footer.copyright", { year, businessName: settings.business_name })}
        </div>
        {settings.payment_icons?.length ? (
          <ul class="footer-payment-icons" aria-label={tStatic(locale, "footer.paymentMethods")}>
            {settings.payment_icons.map((icon) => (
              <li key={`${icon.label}-${icon.icon_url}`}>
                <img src={icon.icon_url} alt={icon.label} width={48} height={28} loading="lazy" />
              </li>
            ))}
          </ul>
        ) : null}
      </div>
    </footer>
  );
});
