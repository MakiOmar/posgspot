import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { HomepagePromoBanner } from "~/lib/types";

interface PromoBannerSectionProps {
  banner: HomepagePromoBanner;
}

function resolveHref(link: string, locale: "en" | "ar"): string | null {
  const trimmed = link.trim();
  if (!trimmed) {
    return null;
  }
  if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith("mailto:") || trimmed.startsWith("tel:")) {
    return trimmed;
  }
  if (trimmed.startsWith("/")) {
    return localePath(locale, trimmed);
  }
  return localePath(locale, `/${trimmed}`);
}

function positionStyle(pos: HomepagePromoBanner["image_position"]): Record<string, string> {
  return {
    top: pos.top,
    right: pos.right,
    bottom: pos.bottom,
    left: pos.left,
    width: pos.width,
  };
}

/** Compositional homepage promo banner (logo, titles, bg/border, positioned image, CTA). */
export const PromoBannerSection = component$<PromoBannerSectionProps>(({ banner }) => {
  const { locale } = useI18n();
  const href = resolveHref(banner.button.link, locale);
  const isExternal = Boolean(href && /^https?:\/\//i.test(href));

  const shellStyle = {
    backgroundColor: banner.background_color,
    borderRadius: `${banner.border_radius}px`,
    borderWidth: `${banner.border_thickness}px`,
    borderStyle: banner.border_thickness > 0 ? "solid" : "none",
    borderColor: banner.border_color,
    minHeight: `${banner.min_height}px`,
  };

  const buttonStyle = {
    ...positionStyle(banner.button.position),
    backgroundColor: banner.button.background_color,
    color: banner.button.text_color,
    borderRadius: `${banner.button.border_radius}px`,
  };

  const buttonInner = (
    <>
      <span>{banner.button.label}</span>
      {banner.button.show_arrow ? (
        <span class="promo-banner-block__arrow" style={{ color: banner.button.arrow_color }} aria-hidden="true">
          ›
        </span>
      ) : null}
    </>
  );

  return (
    <section class="promo-banner-block" aria-label={banner.main_title || banner.top_title || "Promotion"}>
      <div class="promo-banner-block__shell" style={shellStyle}>
        <div class="promo-banner-block__copy">
          {banner.logo_url ? (
            <img class="promo-banner-block__logo" src={banner.logo_url} alt="" width={120} height={120} loading="lazy" />
          ) : null}
          <div class="promo-banner-block__text">
            {banner.top_title ? (
              <p class="promo-banner-block__top-title" style={{ color: banner.top_title_color }}>
                {banner.top_title}
              </p>
            ) : null}
            {banner.main_title ? (
              <h2 class="promo-banner-block__main-title" style={{ color: banner.main_title_color }}>
                {banner.main_title}
              </h2>
            ) : null}
          </div>
        </div>

        {banner.image_url ? (
          <img
            class="promo-banner-block__media"
            src={banner.image_url}
            alt=""
            width={640}
            height={480}
            style={positionStyle(banner.image_position)}
            loading="lazy"
          />
        ) : null}

        {href ? (
          isExternal ? (
            <a href={href} class="promo-banner-block__cta" style={buttonStyle} target="_blank" rel="noopener noreferrer">
              {buttonInner}
            </a>
          ) : (
            <Link href={href} class="promo-banner-block__cta" style={buttonStyle}>
              {buttonInner}
            </Link>
          )
        ) : (
          <span class="promo-banner-block__cta" style={buttonStyle}>
            {buttonInner}
          </span>
        )}
      </div>
    </section>
  );
});
