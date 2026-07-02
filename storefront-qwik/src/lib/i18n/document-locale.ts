import type { RenderToStreamOptions } from "@builder.io/qwik/server";
import { isSupportedLocale, localeDefinition, type StoreLocaleCode } from "./config";
import { localeFromPathname } from "./paths";

/** Resolve storefront locale from SSR request data (route param or URL prefix). */
export function resolveDocumentLocale(opts: RenderToStreamOptions): StoreLocaleCode {
  const params = opts.serverData?.qwikcity?.params as { lang?: string } | undefined;
  if (params?.lang && isSupportedLocale(params.lang)) {
    return params.lang;
  }

  const url = opts.serverData?.url;
  if (typeof url === "string") {
    try {
      return localeFromPathname(new URL(url).pathname);
    } catch {
      // fall through
    }
  }

  return localeFromPathname("/");
}

export function documentHtmlAttributes(
  opts: RenderToStreamOptions,
): Record<string, string> {
  const locale = resolveDocumentLocale(opts);
  const def = localeDefinition(locale);
  return {
    lang: locale,
    dir: def.dir,
  };
}
