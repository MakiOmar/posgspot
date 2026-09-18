/** Storefront layout variants controlled by build-time env (PUBLIC_* / VITE_*). */

export type HeaderStyle = "one" | "two";
export type FontFamily = "default" | "playfair";

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

function parseFontFamily(raw: string | undefined): FontFamily {
  const normalized = (raw ?? "default").toLowerCase().trim();
  if (normalized === "playfair" || normalized === "playfair-display") {
    return "playfair";
  }
  return "default";
}

/**
 * Staging / pre-launch: block all crawlers.
 * Set `PUBLIC_ROBOTS_DISALLOW_ALL=true` in `.env.production` before build.
 * Affects robots.txt, sitemap.xml, meta robots, and X-Robots-Tag.
 */
export const ROBOTS_DISALLOW_ALL: boolean = envFlag("PUBLIC_ROBOTS_DISALLOW_ALL");

/**
 * Header layout:
 * - `one` — single bar (logo + nav + icon actions); search opens a modal (Sigma-style).
 * - `two` — main row (logo, inline search, actions) + second row for nav and categories.
 *
 * Set `PUBLIC_HEADER_STYLE=one` or `PUBLIC_HEADER_STYLE=two` (default: two).
 */
export const HEADER_STYLE: HeaderStyle = parseHeaderStyle(
  envString("PUBLIC_HEADER_STYLE") || envString("VITE_HEADER_STYLE") || "two",
);

/**
 * Latin UI typeface:
 * - `default` — system / Segoe UI stack (current).
 * - `playfair` — Google Font “Playfair Display” (serif headings + UI).
 *
 * Arabic UI keeps Cairo. Set `PUBLIC_FONT_FAMILY=playfair` or `default`.
 */
export const FONT_FAMILY: FontFamily = parseFontFamily(
  envString("PUBLIC_FONT_FAMILY") || envString("VITE_FONT_FAMILY") || "default",
);

/** Laravel POS web origin for remaining external POS links. Defaults to PUBLIC_API_BASE. */
export const POS_WEB_BASE: string = (
  envString("PUBLIC_POS_WEB_BASE") ||
  envString("PUBLIC_API_BASE") ||
  "http://localhost:8000"
)
  .replace(/\/api\/?$/i, "")
  .replace(/\/$/, "");

/** @deprecated External portal replaced by in-app `/track-console`. */
export const TRACK_CONSOLE_URL = "https://accounts.gamesspoteg.com/device/track";
