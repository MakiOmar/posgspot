import { component$, type QRL } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import type { ResolvedNavChild } from "~/lib/header-nav";

function childKey(child: ResolvedNavChild): string {
  return child.href || child.action || child.label;
}

interface ShopMegaTreeProps {
  links: ResolvedNavChild[];
  go$: QRL<(href: string) => Promise<void>>;
  depth?: number;
}

/** Recursive Shop Physical mega column nodes (groups may nest). */
export const ShopMegaTree = component$<ShopMegaTreeProps>(({ links, go$, depth = 0 }) => {
  return (
    <>
      {links.map((child) => {
        const nested = Boolean(child.children?.length);
        if (nested) {
          return (
            <li
              key={childKey(child)}
              class={`header-nav-mega__group header-nav-mega__group--d${depth}`}
              role="none"
            >
              <span class="header-nav-mega__group-title">{child.label}</span>
              <ul class="header-nav-mega__sublist">
                <ShopMegaTree links={child.children!} go$={go$} depth={depth + 1} />
              </ul>
            </li>
          );
        }
        return (
          <li key={childKey(child)} role="none">
            {child.disabled || !child.href ? (
              <span class="header-nav-dropdown__option header-nav-dropdown__option--disabled">
                {child.label}
                {child.hint ? ` (${child.hint})` : ""}
              </span>
            ) : child.href.startsWith("tel:") ? (
              <a href={child.href} class="header-nav-dropdown__option" role="menuitem">
                {child.label}
              </a>
            ) : (
              <button
                type="button"
                class="header-nav-dropdown__option"
                role="menuitem"
                onClick$={() => go$(child.href!)}
              >
                {child.label}
              </button>
            )}
          </li>
        );
      })}
    </>
  );
});

interface ShopDrawerTreeProps {
  links: ResolvedNavChild[];
  onClose$: QRL<() => void>;
  depth?: number;
}

/** Recursive Shop Physical nodes for the mobile nav drawer. */
export const ShopDrawerTree = component$<ShopDrawerTreeProps>(
  ({ links, onClose$, depth = 0 }) => {
    return (
      <>
        {links.map((child) => {
          const nested = Boolean(child.children?.length);
          if (nested) {
            return (
              <li
                key={childKey(child)}
                class={`side-drawer-mega-group side-drawer-mega-group--d${depth}`}
              >
                <span class="side-drawer-mega-group-title">{child.label}</span>
                <ul class="side-drawer-sublist side-drawer-sublist--nested">
                  <ShopDrawerTree links={child.children!} onClose$={onClose$} depth={depth + 1} />
                </ul>
              </li>
            );
          }
          return (
            <li key={childKey(child)}>
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
                  prefetch={false}
                  onClick$={onClose$}
                >
                  {child.label}
                </Link>
              )}
            </li>
          );
        })}
      </>
    );
  },
);
