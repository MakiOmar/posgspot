import type { RequestHandler } from "@builder.io/qwik-city";
import { ROBOTS_DISALLOW_ALL } from "~/lib/config";

/**
 * Machine-readable site summary for LLM / agentic crawlers (Lighthouse llms.txt audit).
 * Keep short: H1 title + absolute links to key public surfaces.
 */
export function buildLlmsTxt(origin: string): string {
  const base = origin.replace(/\/$/, "");
  const lines = [
    "# Games Spot Egypt",
    "",
    "> Official storefront for Games Spot — consoles, games, accessories, digital catalog, repairs, and store locations in Egypt.",
    "",
    "## Site",
    "",
    `- Home (EN): ${base}/en/`,
    `- Home (AR): ${base}/ar/`,
    `- Products: ${base}/en/products/`,
    `- Digital games: ${base}/en/games/`,
    `- Gift cards: ${base}/en/gift-cards/`,
    `- Stores: ${base}/en/stores/`,
    `- FAQ: ${base}/en/faq/`,
    `- Contact: ${base}/en/contact/`,
    `- Sitemap: ${base}/sitemap.xml`,
    `- Robots: ${base}/robots.txt`,
    "",
  ];

  if (ROBOTS_DISALLOW_ALL) {
    lines.push(
      "## Indexing",
      "",
      "This deployment currently disallows crawlers (`PUBLIC_ROBOTS_DISALLOW_ALL`). Prefer the production shop host when indexing.",
      "",
    );
  }

  return `${lines.join("\n")}\n`;
}

export const onGet: RequestHandler = ({ url, headers, send }) => {
  headers.set("Content-Type", "text/plain; charset=utf-8");
  headers.set("Cache-Control", "public, max-age=3600");
  send(200, buildLlmsTxt(url.origin));
};
