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

export const ABOUT_TIMELINE: AboutTimelineEntry[] = [
  {
    year: "2021",
    title: "Nasr City flagship",
    text: "Expanded our Nasr City location with a brighter showroom floor, more consoles on display, and faster in-store pickup for online orders.",
    image: "/NASR-CITY-BRUNCH.webp",
    imageAlt: "Games Spot Nasr City branch interior",
  },
  {
    year: "2020",
    title: "New Cairo — Mega Mall",
    text: "Opened on the second floor of Mega Mall on North 90th Street, bringing PlayStation, Xbox, and accessories closer to New Cairo gamers.",
    image: "/NEW-CAIRO-BRUNCH.webp",
    imageAlt: "Games Spot New Cairo Mega Mall branch",
  },
  {
    year: "2019",
    title: "Sheikh Zayed — Beverly Hills",
    text: "Launched at West Square Mall in Beverly Hills, Sheikh Zayed—our first major west Cairo branch with full repair desk support.",
    image: "/BEVERLLY-HILLS-BRUNCH.webp",
    imageAlt: "Games Spot Beverly Hills West Square Mall branch",
  },
  {
    year: "2018",
    title: "Where it all started",
    text: "Games Spot began with a simple mission: make gaming accessible, affordable, and fun for everyone in Egypt—with honest advice and fair prices.",
  },
  {
    year: "2017",
    title: "The idea takes shape",
    text: "Our founders mapped out the first store concept—a neighborhood shop built around community, trade-ins, and trusted console repairs.",
  },
];

export function getAboutTimeline(locale: StoreLocaleCode): AboutTimelineEntry[] {
  return locale === "ar" ? ABOUT_TIMELINE_AR : ABOUT_TIMELINE;
}
