import { component$, type QRL, useSignal } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import type { ResolvedNavChild } from "~/lib/header-nav";
import { tStatic, useI18n } from "~/lib/i18n/context";

function childKey(child: ResolvedNavChild): string {
  return child.href || child.action || child.label;
}

interface ShopMegaTreeProps {
  links: ResolvedNavChild[];
  go$: QRL<(href: string) => Promise<void>>;
  depth?: number;
}

/** Recursive Shop Physical mega column nodes (groups folded by default). */
export const ShopMegaTree = component$<ShopMegaTreeProps>(({ links, go$, depth = 0 }) => {
  return (
    <>
      {links.map((child) => {
        const nested = Boolean(child.children?.length);
        if (nested) {
          return (
            <ShopMegaGroup
              key={childKey(child)}
              child={child}
              go$={go$}
              depth={depth}
            />
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

const ShopMegaGroup = component$<{
  child: ResolvedNavChild;
  go$: QRL<(href: string) => Promise<void>>;
  depth: number;
}>(({ child, go$, depth }) => {
  const { locale } = useI18n();
  const open = useSignal(false);
  const panelId = `shop-mega-${depth}-${childKey(child).replace(/\s+/g, "-")}`;

  return (
    <li
      class={`header-nav-mega__group header-nav-mega__group--d${depth}${open.value ? " is-open" : ""}`}
      role="none"
    >
      <button
        type="button"
        class="header-nav-mega__group-toggle"
        aria-expanded={open.value ? "true" : "false"}
        aria-controls={panelId}
        onClick$={() => {
          open.value = !open.value;
        }}
      >
        <span class="header-nav-mega__group-title">{child.label}</span>
        <span class="header-nav-mega__group-chevron" aria-hidden="true">
          {open.value ? "▾" : "▸"}
        </span>
        <span class="sr-only">
          {open.value
            ? tStatic(locale, "a11y.collapseMenu")
            : tStatic(locale, "a11y.expandMenu")}
        </span>
      </button>
      {open.value ? (
        <ul id={panelId} class="header-nav-mega__sublist">
          <ShopMegaTree links={child.children!} go$={go$} depth={depth + 1} />
        </ul>
      ) : null}
    </li>
  );
});

interface ShopDrawerTreeProps {
  links: ResolvedNavChild[];
  onClose$: QRL<() => void>;
  depth?: number;
}

/** Recursive Shop Physical nodes for the mobile nav drawer (groups folded by default). */
export const ShopDrawerTree = component$<ShopDrawerTreeProps>(
  ({ links, onClose$, depth = 0 }) => {
    return (
      <>
        {links.map((child) => {
          const nested = Boolean(child.children?.length);
          if (nested) {
            return (
              <ShopDrawerGroup
                key={childKey(child)}
                child={child}
                onClose$={onClose$}
                depth={depth}
              />
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

const ShopDrawerGroup = component$<{
  child: ResolvedNavChild;
  onClose$: QRL<() => void>;
  depth: number;
}>(({ child, onClose$, depth }) => {
  const { locale } = useI18n();
  const open = useSignal(false);
  const panelId = `shop-drawer-${depth}-${childKey(child).replace(/\s+/g, "-")}`;

  return (
    <li
      class={`side-drawer-mega-group side-drawer-mega-group--d${depth}${open.value ? " is-open" : ""}`}
    >
      <button
        type="button"
        class="side-drawer-mega-group-toggle"
        aria-expanded={open.value ? "true" : "false"}
        aria-controls={panelId}
        onClick$={() => {
          open.value = !open.value;
        }}
      >
        <span class="side-drawer-mega-group-title">{child.label}</span>
        <span class="side-drawer-mega-group-chevron" aria-hidden="true">
          {open.value ? "▾" : "▸"}
        </span>
        <span class="sr-only">
          {open.value
            ? tStatic(locale, "a11y.collapseMenu")
            : tStatic(locale, "a11y.expandMenu")}
        </span>
      </button>
      {open.value ? (
        <ul id={panelId} class="side-drawer-sublist side-drawer-sublist--nested">
          <ShopDrawerTree links={child.children!} onClose$={onClose$} depth={depth + 1} />
        </ul>
      ) : null}
    </li>
  );
});
