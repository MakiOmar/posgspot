import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { localePath } from "~/lib/i18n/paths";
import { useI18n } from "~/lib/i18n/context";
import type { PromoBanner } from "~/lib/types";

interface PromoBannersProps {
  banners: PromoBanner[];
  placement: "home" | "category";
  categorySlug?: string | null;
  class?: string;
}

function resolveBannerHref(link: string, locale: "en" | "ar"): string | null {
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

/** Admin-managed promo images for homepage or a category PLP. */
export const PromoBanners = component$<PromoBannersProps>((props) => {
  const { locale } = useI18n();
  const items = props.banners.filter((banner) => {
    if (banner.placement !== props.placement) {
      return false;
    }
    if (props.placement === "category") {
      return Boolean(props.categorySlug) && banner.category_slug === props.categorySlug;
    }
    return true;
  });

  if (items.length === 0) {
    return null;
  }

  return (
    <section
      class={`promo-banners${props.class ? ` ${props.class}` : ""}`}
      aria-label={props.placement === "home" ? "Promotions" : "Category promotions"}
    >
      <ul class="promo-banners__list">
        {items.map((banner) => {
          const href = resolveBannerHref(banner.link, locale);
          const img = (
            <img
              class="promo-banners__image"
              src={banner.image_url}
              alt={banner.title || ""}
              width={1200}
              height={400}
              loading="lazy"
            />
          );

          return (
            <li key={banner.id || banner.image_url} class="promo-banners__item">
              {href ? (
                /^https?:\/\//i.test(href) ? (
                  <a href={href} class="promo-banners__link" target="_blank" rel="noopener noreferrer">
                    {img}
                  </a>
                ) : (
                  <Link href={href} class="promo-banners__link">
                    {img}
                  </Link>
                )
              ) : (
                <div class="promo-banners__link">{img}</div>
              )}
            </li>
          );
        })}
      </ul>
    </section>
  );
});
