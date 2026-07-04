import { $, component$, useOnDocument, useSignal } from "@builder.io/qwik";
import { Link, useLocation } from "@builder.io/qwik-city";
import { STORE_LOCALES, localeDefinition, type StoreLocaleCode } from "~/lib/i18n/config";
import { localeFromPathname, swapLocalePath } from "~/lib/i18n/paths";
import type { StoreSettings } from "~/lib/types";

interface LanguageSwitcherProps {
  settings: StoreSettings;
}

export const LanguageSwitcher = component$<LanguageSwitcherProps>(({ settings }) => {
  const loc = useLocation();
  const open = useSignal(false);
  const enabled = new Set(settings.locales ?? ["en", "ar"]);
  const activeCode = localeFromPathname(loc.url.pathname);
  const active = localeDefinition(activeCode);

  const alternatives = STORE_LOCALES.filter(
    (l) => enabled.has(l.code) && l.code !== activeCode,
  );

  const close$ = $(() => {
    open.value = false;
  });

  const toggle$ = $(() => {
    open.value = !open.value;
  });

  // Close when clicking outside the switcher.
  useOnDocument(
    "click",
    $((event) => {
      const target = event.target as HTMLElement | null;
      if (!target?.closest(".language-switcher")) {
        open.value = false;
      }
    }),
  );

  useOnDocument(
    "keydown",
    $((event) => {
      if ((event as KeyboardEvent).key === "Escape") {
        open.value = false;
      }
    }),
  );

  if (alternatives.length === 0) {
    return (
      <div class="language-switcher" role="navigation" aria-label="Language">
        <span class="language-switcher__trigger language-switcher__trigger--static" aria-current="true">
          <span class="language-switcher__flag" aria-hidden="true">
            {active.flag}
          </span>
          <span class="language-switcher__label">{active.label}</span>
        </span>
      </div>
    );
  }

  return (
    <div class={`language-switcher${open.value ? " language-switcher--open" : ""}`} role="navigation" aria-label="Language">
      <button
        type="button"
        class="language-switcher__trigger"
        aria-haspopup="listbox"
        aria-expanded={open.value}
        aria-current="true"
        onClick$={toggle$}
      >
        <span class="language-switcher__flag" aria-hidden="true">
          {active.flag}
        </span>
        <span class="language-switcher__label">{active.label}</span>
        <span class="language-switcher__caret" aria-hidden="true" />
      </button>

      {open.value ? (
        <ul class="language-switcher__menu" role="listbox" aria-label="Other languages">
          {alternatives.map((locale) => {
            const href = swapLocalePath(
              loc.url.pathname,
              loc.url.searchParams.toString(),
              locale.code as StoreLocaleCode,
            );

            return (
              <li key={locale.code} role="option">
                <Link href={href} class="language-switcher__option" onClick$={close$}>
                  <span class="language-switcher__flag" aria-hidden="true">
                    {locale.flag}
                  </span>
                  <span class="language-switcher__option-text">
                    <span class="language-switcher__option-label">{locale.label}</span>
                    <span class="language-switcher__option-name">{locale.name}</span>
                  </span>
                </Link>
              </li>
            );
          })}
        </ul>
      ) : null}
    </div>
  );
});
