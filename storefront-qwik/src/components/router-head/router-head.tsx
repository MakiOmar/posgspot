import { component$, useVisibleTask$ } from "@builder.io/qwik";
import { useDocumentHead, useLocation } from "@builder.io/qwik-city";
import { setActiveContentLocale } from "~/lib/api";
import {
  arabicFontStylesheetHref,
  needsArabicFont,
  shouldPreconnectGoogleFonts,
} from "~/lib/fonts/document-head";
import { localeDefinition } from "~/lib/i18n/config";
import { localeFromPathname } from "~/lib/i18n/paths";

/**
 * The RouterHead component is placed inside of the document `<head>` element.
 */
export const RouterHead = component$(() => {
  const head = useDocumentHead();
  const loc = useLocation();
  const locale = localeFromPathname(loc.url.pathname);
  const loadArabicFont = needsArabicFont(locale);

  // Keep html lang/dir in sync on client navigations (language switcher).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => loc.url.pathname);
    const active = localeFromPathname(loc.url.pathname);
    const activeDef = localeDefinition(active);
    setActiveContentLocale(active);
    document.documentElement.lang = active;
    document.documentElement.dir = activeDef.dir;
  });

  const hasCanonical = head.links.some((link) => link.rel === "canonical");

  return (
    <>
      <title>{head.title}</title>

      {/* Prefer route-supplied canonical (locale-aware); fall back to the request URL. */}
      {!hasCanonical ? <link rel="canonical" href={loc.url.href} /> : null}
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <link rel="icon" type="image/svg+xml" href="/favicon.svg" />

      {loadArabicFont && shouldPreconnectGoogleFonts() ? (
        <>
          <link rel="preconnect" href="https://fonts.googleapis.com" />
          <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
        </>
      ) : null}

      {loadArabicFont ? (
        <link rel="stylesheet" href={arabicFontStylesheetHref()} />
      ) : null}

      {head.meta.map((m) => (
        <meta key={m.key} {...m} />
      ))}

      {head.links.map((l) => (
        <link key={l.key} {...l} />
      ))}

      {head.styles.map((s) => (
        <style
          key={s.key}
          {...s.props}
          {...(s.props?.dangerouslySetInnerHTML
            ? {}
            : { dangerouslySetInnerHTML: s.style })}
        />
      ))}

      {head.scripts.map((s) => (
        <script
          key={s.key}
          {...s.props}
          {...(s.props?.dangerouslySetInnerHTML
            ? {}
            : { dangerouslySetInnerHTML: s.script })}
        />
      ))}
    </>
  );
});
