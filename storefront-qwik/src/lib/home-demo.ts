/**
 * Demo homepage content mirrored from https://gamesspoteg.com/
 * Hero / promo / video use live WP media URLs as specified.
 * Promo tiles are a temporary stand-in for a future Accounts API section.
 */

const WP = "https://gamesspoteg.com/wp-content/uploads";

export interface HomeHeroSlide {
  id: string;
  imageUrl: string;
  /** Storefront-relative path (prefixed with locale at render time). */
  href: string;
  kicker: string;
  title: string;
}

export interface HomePromoTile {
  id: string;
  imageUrl: string;
  href: string;
  label: string;
}

export const HOME_HERO_SLIDES: HomeHeroSlide[] = [
  {
    id: "slide-1",
    imageUrl: `${WP}/2024/03/bg-slider-1.png`,
    href: "/products",
    kicker: "Witness Play Unleashed™",
    title: "PS5 Ghost of Yotei Gold Limited Edition Bundle",
  },
  {
    id: "slide-2",
    imageUrl: `${WP}/2024/03/home1-slide2.png`,
    href: "/products",
    kicker: "wireless controller",
    title: "DualSense® 007 First Light™",
  },
  {
    id: "slide-3",
    imageUrl: `${WP}/2024/03/home1-slide3.png`,
    href: "/products",
    kicker: "PULSE™ wireless headset",
    title: "A new era in gaming audio",
  },
];

/** Temporary demo tiles — replace with Accounts API response later. */
export const HOME_PROMO_TILES: HomePromoTile[] = [
  {
    id: "promo-main",
    imageUrl: `${WP}/2026/06/26517668.jpg.webp`,
    href: "/products",
    label: "007 First Light",
  },
  {
    id: "promo-2",
    imageUrl: `${WP}/2025/10/IMG_2392.jpeg`,
    href: "/products",
    label: "Shop now",
  },
  {
    id: "promo-3",
    imageUrl: `${WP}/2025/10/IMG_2393-scaled.jpeg`,
    href: "/products",
    label: "Shop now",
  },
  {
    id: "promo-4",
    imageUrl: `${WP}/2026/06/thumb-1920-1397346-1.jpg`,
    href: "/products",
    label: "Shop now",
  },
];

export const HOME_VIDEO = {
  src: `${WP}/2026/06/Grand-Theft-Auto-VI-Trailer-2.mp4`,
  poster: `${WP}/2026/06/poster_full.0az_iud2g3y4j.jpg`,
} as const;
