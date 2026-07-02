/** Paths that must not be indexed (account, checkout, auth, utilities). */
export const ROBOTS_DISALLOW_PREFIXES = [
  "/cart",
  "/checkout",
  "/login",
  "/register",
  "/forgot-password",
  "/reset-password",
  "/account",
  "/add-customer",
  "/api-test",
] as const;

export function buildRobotsTxt(origin: string): string {
  const lines = [
    "User-agent: *",
    ...ROBOTS_DISALLOW_PREFIXES.map((path) => `Disallow: ${path}`),
    "",
    `Sitemap: ${origin.replace(/\/$/, "")}/sitemap.xml`,
  ];

  return `${lines.join("\n")}\n`;
}

const STATIC_SITEMAP_PATHS = [
  "/",
  "/products",
  "/contact",
  "/about",
  "/faq",
  "/terms-and-conditions",
  "/privacy-policy",
  "/return-policy",
] as const;

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

export function staticSitemapPaths(): string[] {
  return [...STATIC_SITEMAP_PATHS];
}
