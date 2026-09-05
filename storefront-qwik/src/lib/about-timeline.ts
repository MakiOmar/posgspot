/** About page timeline entries — images live in `storefront-qwik/public/`. */

import type { StoreLocaleCode } from "./i18n/config";
import { ABOUT_TIMELINE_AR } from "./about-timeline-ar";

export interface AboutTimelineEntry {
  year: string;
  title: string;
  text: string;
  /** Public URL path, e.g. `/NASR-CITY-BRUNCH.webp` */
  image?: string;
  imageAlt?: string;
}

/** Matches https://gamesspoteg.com/about-us/ branch timeline (years + openings). */
export const ABOUT_TIMELINE: AboutTimelineEntry[] = [
  {
    year: "2021",
    title: "Nasr City branch founded",
    text: "We believe in giving each customer access to exceptional products that are expertly created to give only positive experiences.",
    image: "/NASR-CITY-BRUNCH.webp",
    imageAlt: "Games Spot Nasr City branch",
  },
  {
    year: "2023",
    title: "New Cairo branch founded",
    text: "Each store reflects our commitment to bringing the gaming experience to life, offering a place where every visit feels like an adventure.",
    image: "/NEW-CAIRO-BRUNCH.webp",
    imageAlt: "Games Spot New Cairo branch",
  },
  {
    year: "2024",
    title: "Beverly Hills branch founded",
    text: "Designed with the gaming community in mind, each location offers a unique blend of modern aesthetics and nostalgic charm to create an inviting atmosphere.",
    image: "/BEVERLLY-HILLS-BRUNCH.webp",
    imageAlt: "Games Spot Beverly Hills branch",
  },
  {
    year: "2025",
    title: "El Shourouk branch founded",
    text: "We believe in giving each customer access to exceptional products that are expertly created to give only positive experiences.",
  },
  {
    year: "2026",
    title: "Alexandria branch founded",
    text: "Our stores are hubs of excitement, connection, and discovery—bringing the Games Spot experience to Alexandria gamers.",
  },
];

export function getAboutTimeline(locale: StoreLocaleCode): AboutTimelineEntry[] {
  return locale === "ar" ? ABOUT_TIMELINE_AR : ABOUT_TIMELINE;
}
