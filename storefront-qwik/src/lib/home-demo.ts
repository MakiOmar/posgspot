/**
 * Legacy demo homepage media (pre-section-builder).
 *
 * Live homepage content is seeded server-side into `homepage_sections`
 * (see HomepageSectionService::defaultSections) and served via GET /homepage.
 * This file is kept only as a historical reference — do not import in routes.
 */

const WP = "https://gamesspoteg.com/wp-content/uploads";

/** @deprecated Use GET /homepage hero_slider settings. */
export const HOME_HERO_SLIDES = [
  {
    id: "slide-1",
    imageUrl: `${WP}/2024/03/bg-slider-1.png`,
    href: "/products",
    kicker: "Witness Play Unleashed™",
    title: "PS5 Ghost of Yotei Gold Limited Edition Bundle",
  },
] as const;

/** @deprecated Use GET /homepage promo_tiles settings. */
export const HOME_PROMO_TILES = [] as const;

/** @deprecated Use GET /homepage video settings. */
export const HOME_VIDEO = {
  src: `${WP}/2026/06/Grand-Theft-Auto-VI-Trailer-2.mp4`,
  poster: `${WP}/2026/06/poster_full.0az_iud2g3y4j.jpg`,
} as const;
