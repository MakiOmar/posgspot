/** Storefront layout variants controlled by build-time env (PUBLIC_* / VITE_*). */

export type HeaderStyle = "one" | "two";

function parseHeaderStyle(raw: string | undefined): HeaderStyle {
  const normalized = (raw ?? "two").toLowerCase().trim();
  if (normalized === "1" || normalized === "one") {
    return "one";
  }
  return "two";
}

/**
 * Header layout:
 * - `one` — logo, Home/Shop/Categories, search, and actions on one row.
 * - `two` — main row (logo, search, actions) + second row for nav and categories.
 *
 * Set `PUBLIC_HEADER_STYLE=one` or `PUBLIC_HEADER_STYLE=two` (default: two).
 */
export const HEADER_STYLE: HeaderStyle = parseHeaderStyle(
  (import.meta.env.PUBLIC_HEADER_STYLE as string | undefined) ??
    (import.meta.env.VITE_HEADER_STYLE as string | undefined),
);

/** Laravel POS web origin (repair status, etc.). Defaults to PUBLIC_API_BASE. */
export const POS_WEB_BASE: string = (
  (import.meta.env.PUBLIC_POS_WEB_BASE as string | undefined) ??
    (import.meta.env.PUBLIC_API_BASE as string | undefined) ??
    "http://localhost:8000"
)
  .replace(/\/api\/?$/i, "")
  .replace(/\/$/, "");

export const REPAIR_STATUS_URL = `${POS_WEB_BASE}/repair-status`;

export const TRACK_CONSOLE_URL = "https://accounts.gamesspoteg.com/device/track";
