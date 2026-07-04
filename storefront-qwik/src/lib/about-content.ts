/** Static About page marketing copy for the public storefront. */

import type { StoreLocaleCode } from "./i18n/config";
import { ABOUT_CONTENT_AR, type AboutPageContent } from "./about-content-ar";

export type { AboutPageContent, AboutTeamMember, AboutVisionItem } from "./about-content-ar";

export const ABOUT_CONTENT_EN: AboutPageContent = {
  kicker: "Play to your level",
  lead: "Egypt's home for consoles, games, accessories, repairs, and expert advice—online and in-store.",
  whoWeAreTitle: "Who we are",
  whoWeAreBody:
    "is a gaming retailer built by players, for players. From the latest PlayStation and Xbox hardware to pre-owned bargains, digital codes, and fast repairs, we help you get more from every session. Our team lives and breathes games—so you always get honest recommendations and support long after checkout.",
  yearsLabel: "years of experience",
  customersLabel: "happy customers",
  visionTitle: "Our vision",
  visionItems: [
    {
      title: "Quality Products",
      text: "We stock genuine consoles, accessories, and games from trusted suppliers so you can play with confidence.",
    },
    {
      title: "Competitive Prices",
      text: "Fair pricing on new and pre-owned gear, with regular deals across our branches and online store.",
    },
    {
      title: "Customer Focus",
      text: "Friendly advice in-store and online—whether you are buying your first console or upgrading your setup.",
    },
    {
      title: "Honesty & Integrity",
      text: "Clear warranties, transparent grading on used items, and repair updates you can trust.",
    },
  ],
  historyTitle: "Making history together",
  historyIntro:
    "Every branch opening and every repair completed is part of our story with the gaming community in Egypt.",
  teamTitle: "Our team",
  team: [
    { name: "Mohamed Salah", role: "CEO & Founder" },
    { name: "Ahmed Hassan", role: "Operations Manager" },
    { name: "Karim Ali", role: "Head of Repairs" },
    { name: "Sara Mahmoud", role: "Customer Experience" },
    { name: "Omar Nabil", role: "Retail Lead" },
  ],
};

export function getAboutContent(locale: StoreLocaleCode): AboutPageContent {
  return locale === "ar" ? ABOUT_CONTENT_AR : ABOUT_CONTENT_EN;
}
