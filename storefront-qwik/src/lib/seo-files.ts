/** Paths that must not be indexed (account, checkout, auth, utilities). */
import { ROBOTS_DISALLOW_ALL } from "~/lib/config";
import { STORE_LOCALES } from "~/lib/i18n/config";
import { localePath } from "~/lib/i18n/paths";

const ROBOTS_DISALLOW_SUFFIXES = [
  "/cart",
  "/checkout",
  "/login",
  "/register",
  "/forgot-password",
  "/reset-password",
  "/account",
  "/add-customer",
  "/api-test",
  "/search",
] as const;

export function robotsDisallowPaths(): string[] {
  const paths: string[] = [];
  for (const loc of STORE_LOCALES) {
    for (const suffix of ROBOTS_DISALLOW_SUFFIXES) {
      paths.push(localePath(loc.code, suffix));
    }
  }
  return paths;
}

/**
 * Build robots.txt body.
 * When `PUBLIC_ROBOTS_DISALLOW_ALL` is set, emit a full-site disallow and omit Sitemap.
 */
export function buildRobotsTxt(origin: string): string {
  if (ROBOTS_DISALLOW_ALL) {
    return ["User-agent: *", "Disallow: /", ""].join("\n");
  }

  const lines = [
    "User-agent: *",
    ...robotsDisallowPaths().map((path) => `Disallow: ${path}`),
    "",
    `Sitemap: ${origin.replace(/\/$/, "")}/sitemap.xml`,
  ];

  return `${lines.join("\n")}\n`;
}

const STATIC_SITEMAP_SUFFIXES = [
  "/",
  "/products",
  "/games",
  "/gift-cards",
  "/brands",
  "/stores",
  "/contact",
  "/about",
  "/faq",
  "/repair-status",
  "/terms-and-conditions",
  "/privacy-policy",
  "/return-policy",
] as const;

export function staticSitemapPaths(): string[] {
  const paths: string[] = [];
  for (const loc of STORE_LOCALES) {
    for (const suffix of STATIC_SITEMAP_SUFFIXES) {
      paths.push(localePath(loc.code, suffix));
    }
  }
  return paths;
}

export function buildSitemapXml(origin: string, paths: string[]): string {
  const base = origin.replace(/\/$/, "");
  const urls = paths
    .map(
      (path) => `  <url>
    <loc>${base}${path.startsWith("/") ? path : `/${path}`}</loc>
  </url>`,
    )
    .join("\n");

  return `<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
${urls}
</urlset>
`;
}
