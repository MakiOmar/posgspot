import type { DocumentHeadValue } from "@builder.io/qwik-city";
import { themeHeadStyleFromSettings } from "~/lib/theme";
import type { StoreSettings } from "~/lib/types";

/**
 * Ensures every route head includes accent CSS variables.
 * Child route heads can replace layout styles during SPA navigation.
 */
export function withStorefrontThemeHead(
  head: DocumentHeadValue,
  settings: StoreSettings,
): DocumentHeadValue {
  const themeStyle = themeHeadStyleFromSettings(settings);
  const styles = head.styles ?? [];
  const hasTheme = styles.some((style) => style.key === "storefront-theme-vars");

  return {
    ...head,
    styles: hasTheme ? styles : [themeStyle, ...styles],
  };
}
