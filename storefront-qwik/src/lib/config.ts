/** Storefront layout variants controlled by build-time env (PUBLIC_* / VITE_*). */

export type HeaderStyle = "one" | "two";

function envString(name: string): string {
  const fromProcess =
    typeof process !== "undefined" ? process.env[name] : undefined;
  const fromImport = (import.meta.env as Record<string, string | undefined>)[name];
  return (fromProcess || fromImport || "").trim();
}

function envFlag(name: string): boolean {
  const raw = envString(name).toLowerCase();
  return raw === "1" || raw === "true" || raw === "yes";
}

function parseHeaderStyle(raw: string | undefined): HeaderStyle {
  const normalized = (raw ?? "two").toLowerCase().trim();
  if (normalized === "1" || normalized === "one") {
    return "one";
  }
  return "two";
}

/**
 * Staging / pre-launch: block all crawlers.
 * Set `PUBLIC_ROBOTS_DISALLOW_ALL=true` in `.env.production` before build.
 * Affects robots.txt, sitemap.xml, meta robots, and X-Robots-Tag.
 */
export const ROBOTS_DISALLOW_ALL: boolean = envFlag("PUBLIC_ROBOTS_DISALLOW_ALL");

/**
 * Header layout:
 * - `one` — logo, Home/Shop/Categories, search, and actions on one row.
 * - `two` — main row (logo, search, actions) + second row for nav and categories.
 *
 * Set `PUBLIC_HEADER_STYLE=one` or `PUBLIC_HEADER_STYLE=two` (default: two).
 */
export const HEADER_STYLE: HeaderStyle = parseHeaderStyle(
  envString("PUBLIC_HEADER_STYLE") || envString("VITE_HEADER_STYLE") || "two",
);

/** Laravel POS web origin for remaining external POS links. Defaults to PUBLIC_API_BASE. */
export const POS_WEB_BASE: string = (
  envString("PUBLIC_POS_WEB_BASE") ||
  envString("PUBLIC_API_BASE") ||
  "http://localhost:8000"
)
  .replace(/\/api\/?$/i, "")
  .replace(/\/$/, "");

export const TRACK_CONSOLE_URL = "https://accounts.gamesspoteg.com/device/track";
