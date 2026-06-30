/** Validate storefront accent hex from settings before applying as CSS. */
export function safeAccent(color: string | undefined): string {
  return color && /^#[0-9a-fA-F]{6}$/.test(color) ? color : "#00d4aa";
}

/** CSS custom properties for theme accent (applied on :root). */
export function themeAccentCss(accent: string): string {
  return `:root{--gs-accent:${accent};--gs-accent-hover:${accent};}`;
}

/** Apply accent variables on the document root (client-side). */
export function applyThemeAccent(accent: string): void {
  if (typeof document === "undefined") {
    return;
  }
  document.documentElement.style.setProperty("--gs-accent", accent);
  document.documentElement.style.setProperty("--gs-accent-hover", accent);
}
