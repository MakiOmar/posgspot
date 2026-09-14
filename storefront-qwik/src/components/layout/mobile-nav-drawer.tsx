import { component$, type QRL } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { CloseIcon } from "~/components/icons";
import type { ResolvedNavItem } from "~/lib/header-nav";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface MobileNavDrawerProps {
  links: ResolvedNavItem[];
  open: boolean;
  onClose$: QRL<() => void>;
}

/**
 * Mobile main-nav side drawer (same pattern as CategoriesDrawer).
 */
export const MobileNavDrawer = component$<MobileNavDrawerProps>(
  ({ links, open, onClose$ }) => {
    const { locale } = useI18n();

    return (
      <div
        class={`side-drawer mobile-nav-drawer${open ? " side-drawer--open" : ""}`}
        aria-hidden={!open}
      >
        <button
          type="button"
          class="side-drawer-backdrop"
          aria-label={tStatic(locale, "common.cancel")}
          onClick$={onClose$}
        />
        <aside
          id="mobile-nav-panel"
          class="side-drawer-panel"
          role="dialog"
          aria-modal="true"
          aria-label={tStatic(locale, "nav.menu")}
        >
          <div class="side-drawer-head">
            <h2 class="side-drawer-title">{tStatic(locale, "nav.menu")}</h2>
            <button
              type="button"
              class="side-drawer-close"
              aria-label={tStatic(locale, "common.cancel")}
              onClick$={onClose$}
            >
              <CloseIcon size={22} />
            </button>
          </div>
          <nav class="side-drawer-nav" aria-label={tStatic(locale, "header.mainNav")}>
            <ul class="side-drawer-list">
              {links.map((item) => {
                if (item.children && item.children.length > 0) {
                  return (
                    <li key={item.label} class="side-drawer-group">
                      <span class="side-drawer-group-label">{item.label}</span>
                      <ul class="side-drawer-sublist">
                        {item.children.map((child) => (
                          <li key={child.href || child.label}>
                            {child.disabled || !child.href ? (
                              <span class="side-drawer-link side-drawer-link--disabled">
                                {child.label}
                                {child.hint ? ` (${child.hint})` : ""}
                              </span>
                            ) : child.href.startsWith("tel:") ? (
                              <a href={child.href} class="side-drawer-link" onClick$={onClose$}>
                                {child.label}
                              </a>
                            ) : (
                              <Link
                                href={child.href}
                                class="side-drawer-link"
                                onClick$={onClose$}
                              >
                                {child.label}
                              </Link>
                            )}
                          </li>
                        ))}
                      </ul>
                    </li>
                  );
                }

                if (item.external && item.href) {
                  return (
                    <li key={item.href}>
                      <a
                        href={item.href}
                        class="side-drawer-link"
                        target="_blank"
                        rel="noopener noreferrer"
                        onClick$={onClose$}
                      >
                        {item.label}
                      </a>
                    </li>
                  );
                }

                if (!item.href) {
                  return null;
                }

                return (
                  <li key={item.href}>
                    <Link
                      href={item.href}
                      class="side-drawer-link"
                      onClick$={onClose$}
                    >
                      {item.label}
                    </Link>
                  </li>
                );
              })}
            </ul>
          </nav>
        </aside>
      </div>
    );
  },
);
