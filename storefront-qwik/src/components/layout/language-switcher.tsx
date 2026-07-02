import { component$ } from "@builder.io/qwik";
import { Link, useLocation } from "@builder.io/qwik-city";
import { STORE_LOCALES, type StoreLocaleCode } from "~/lib/i18n/config";
import { swapLocalePath } from "~/lib/i18n/paths";
import type { StoreSettings } from "~/lib/types";

interface LanguageSwitcherProps {
  settings: StoreSettings;
}

export const LanguageSwitcher = component$<LanguageSwitcherProps>(({ settings }) => {
  const loc = useLocation();
  const enabled = new Set(settings.locales ?? ["en", "ar"]);

  return (
    <div class="language-switcher" role="navigation" aria-label="Language">
      {STORE_LOCALES.filter((l) => enabled.has(l.code)).map((locale, index) => {
        const isActive = loc.url.pathname.startsWith(`/${locale.code}/`) || loc.url.pathname === `/${locale.code}`;
        const href = swapLocalePath(loc.url.pathname, loc.url.searchParams.toString(), locale.code as StoreLocaleCode);

        return (
          <span key={locale.code} class="language-switcher__item">
            {index > 0 ? <span class="language-switcher__sep" aria-hidden="true">|</span> : null}
            {isActive ? (
              <span class="language-switcher__active" aria-current="true">
                {locale.label}
              </span>
            ) : (
              <Link href={href} class="language-switcher__link">
                {locale.label}
              </Link>
            )}
          </span>
        );
      })}
    </div>
  );
});
