import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import {
  FacebookIcon,
  InstagramIcon,
  MapPinIcon,
  TiktokIcon,
  WhatsappIcon,
  YoutubeIcon,
} from "~/components/icons";
import { FooterNewsletter } from "~/components/layout/footer-newsletter";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { StoreLocaleCode } from "~/lib/i18n/config";
import type { StoreLocation, StoreSettings } from "~/lib/types";

interface SiteFooterProps {
  settings: StoreSettings;
  locations: StoreLocation[];
}

function resolveFooterHref(
  locale: StoreLocaleCode,
  url: string,
): { href: string; external: boolean } {
  const trimmed = url.trim();
  if (!trimmed) {
    return { href: localePath(locale, "/"), external: false };
  }
  if (/^https?:\/\//i.test(trimmed)) {
    return { href: trimmed, external: true };
  }
  if (/^(mailto:|tel:)/i.test(trimmed)) {
    return { href: trimmed, external: false };
  }
  const hashIdx = trimmed.indexOf("#");
  const hash = hashIdx >= 0 ? trimmed.slice(hashIdx) : "";
  const pathPart = hashIdx >= 0 ? trimmed.slice(0, hashIdx) || "/" : trimmed;
  const path = pathPart.startsWith("/") ? pathPart : `/${pathPart}`;
  return { href: `${localePath(locale, path)}${hash}`, external: false };
}

function whatsappHref(raw: string): string {
  const digits = raw.replace(/\D/g, "");
  return digits ? `https://wa.me/${digits}` : raw;
}

/** Site footer: locations + social, then up to 3 editable menu columns. */
export const SiteFooter = component$<SiteFooterProps>(({ settings, locations }) => {
  const year = new Date().getFullYear();
  const { locale } = useI18n();
  const footer = settings.footer;
  const contactTitle = footer?.contact_title || tStatic(locale, "footer.contactInfo");
  const columns = (footer?.columns ?? []).slice(0, 3);
  const social = settings.social || {};
  const whatsapp = settings.contact?.whatsapp?.trim() || "";

  return (
    <footer class="site-footer">
      <div class="container footer-grid">
        <div class="footer-col footer-col--contact">
          <h3>{contactTitle}</h3>
          {locations.length > 0 ? (
            <ul class="footer-locations">
              {locations.map((loc) => (
                <li key={loc.id} class="footer-location">
                  <div class="footer-location__head">
                    <MapPinIcon class="footer-location__pin" size={18} />
                    <div>
                      <div class="footer-location__name">{loc.name}</div>
                      {loc.address ? (
                        <p class="footer-location__address">{loc.address}</p>
                      ) : null}
                      <div class="footer-location__actions">
                        {loc.maps_url ? (
                          <a
                            href={loc.maps_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            class="footer-location__action"
                          >
                            {tStatic(locale, "footer.visitUs")}
                          </a>
                        ) : null}
                        {loc.phone ? (
                          <a href={`tel:${loc.phone}`} class="footer-location__action" dir="ltr">
                            {tStatic(locale, "footer.callUs")}
                          </a>
                        ) : null}
                      </div>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p class="footer-muted">{tStatic(locale, "footer.noLocations")}</p>
          )}

          <div class="footer-social" aria-label={tStatic(locale, "footer.followUs")}>
            {social.facebook && social.facebook !== "#" ? (
              <a href={social.facebook} rel="noopener noreferrer" target="_blank" aria-label="Facebook">
                <FacebookIcon size={20} />
              </a>
            ) : null}
            {social.instagram && social.instagram !== "#" ? (
              <a
                href={social.instagram}
                rel="noopener noreferrer"
                target="_blank"
                aria-label="Instagram"
              >
                <InstagramIcon size={20} />
              </a>
            ) : null}
            {social.tiktok && social.tiktok !== "#" ? (
              <a href={social.tiktok} rel="noopener noreferrer" target="_blank" aria-label="TikTok">
                <TiktokIcon size={20} />
              </a>
            ) : null}
            {social.youtube && social.youtube !== "#" ? (
              <a href={social.youtube} rel="noopener noreferrer" target="_blank" aria-label="YouTube">
                <YoutubeIcon size={20} />
              </a>
            ) : null}
            {whatsapp ? (
              <a
                href={whatsappHref(whatsapp)}
                rel="noopener noreferrer"
                target="_blank"
                aria-label="WhatsApp"
              >
                <WhatsappIcon size={20} />
              </a>
            ) : null}
          </div>
        </div>

        {columns.map((col) => (
          <div key={col.id || col.title} class="footer-col">
            <h3>{col.title}</h3>
            <ul>
              {col.links.map((link) => {
                const resolved = resolveFooterHref(locale, link.url);
                return (
                  <li key={link.id || `${link.label}-${link.url}`}>
                    {resolved.external ? (
                      <a href={resolved.href} target="_blank" rel="noopener noreferrer">
                        {link.label}
                      </a>
                    ) : (
                      <Link href={resolved.href}>{link.label}</Link>
                    )}
                  </li>
                );
              })}
            </ul>
          </div>
        ))}
      </div>

      <div class="container footer-bottom">
        <div class="footer-bottom__main">
          <div class="footer-bottom__copy">
            {tStatic(locale, "footer.copyright", { year, businessName: settings.business_name })}
          </div>
          <FooterNewsletter settings={settings} />
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
