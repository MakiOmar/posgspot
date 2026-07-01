import type { StoreSettings } from "~/lib/types";

/** Validate storefront accent hex from settings before applying as CSS. */
export function safeAccent(color: string | undefined): string {
  return color && /^#[0-9a-fA-F]{6}$/.test(color) ? color : "#00d4aa";
}

/** CSS custom properties for theme accent (applied on :root). */
export function themeAccentCss(accent: string): string {
  return `:root{--gs-accent:${accent};--gs-accent-hover:${accent};}`;
}

/** DocumentHead style block — keep key stable so SPA navigations can re-apply it. */
export function themeHeadStyle(accent: string) {
  return {
    key: "storefront-theme-vars",
    props: { id: "storefront-theme-vars" },
    style: themeAccentCss(accent),
  };
}

export function themeHeadStyleFromSettings(settings: StoreSettings) {
  return themeHeadStyle(safeAccent(settings.theme?.accent_color));
}

/** Apply accent variables on the document root (client-side). */
export function applyThemeAccent(accent: string): void {
  if (typeof document === "undefined") {
    return;
  }
  document.documentElement.style.setProperty("--gs-accent", accent);
  document.documentElement.style.setProperty("--gs-accent-hover", accent);
}

/** Keep a persistent style tag so route head updates cannot drop accent tokens. */
export function ensureThemeStyleTag(accent: string): void {
  if (typeof document === "undefined") {
    return;
  }

  const css = themeAccentCss(accent);
  let el = document.getElementById("storefront-theme-vars") as HTMLStyleElement | null;

  if (!el) {
    el = document.createElement("style");
    el.id = "storefront-theme-vars";
    document.head.prepend(el);
  }

  if (el.textContent !== css) {
    el.textContent = css;
  }
}

/** Single entry point for SSR head + client navigations. */
export function syncStorefrontTheme(settings: StoreSettings): void {
  const accent = safeAccent(settings.theme?.accent_color);
  applyThemeAccent(accent);
  ensureThemeStyleTag(accent);
}
