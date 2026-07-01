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
