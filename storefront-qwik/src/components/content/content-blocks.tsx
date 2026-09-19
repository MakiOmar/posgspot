import { $, component$, useOnDocument, useSignal, type QRL } from "@builder.io/qwik";
import { Link, useNavigate } from "@builder.io/qwik-city";
import {
  closeHeaderDropdown,
  useHeaderDropdown,
} from "~/lib/header-dropdown-context";
import type { ResolvedNavChild, ResolvedNavItem } from "~/lib/header-nav";
import { openSupportChatEvent } from "~/lib/support-chat";

interface HeaderNavItemsProps {
  links: ResolvedNavItem[];
  linkClass: string;
}

function childKey(child: ResolvedNavChild): string {
  return child.href || child.action || child.label;
}

export const HeaderNavItems = component$<HeaderNavItemsProps>(({ links, linkClass }) => {
  const headerMenu = useHeaderDropdown();
  const nav = useNavigate();
  const openKey = useSignal<string | null>(null);
  const navOpen = headerMenu.openId === "nav";

  /** Close then navigate so unmounting the menu does not cancel SPA routing. */
  const go$ = $(async (href: string) => {
    openKey.value = null;
    closeHeaderDropdown(headerMenu, "nav");
    await nav(href);
  });

  useOnDocument(
    "click",
    $((event) => {
      const target = event.target as HTMLElement | null;
      if (!target?.closest(".header-nav-dropdown")) {
        openKey.value = null;
        closeHeaderDropdown(headerMenu, "nav");
      }
    }),
  );

  useOnDocument(
    "keydown",
    $((event) => {
      if ((event as KeyboardEvent).key === "Escape") {
        openKey.value = null;
        closeHeaderDropdown(headerMenu, "nav");
      }
    }),
  );

  return (
    <>
      {links.map((item) => {
        const hasMega = Boolean(item.mega?.columns?.length);
        const hasChildren = Boolean(item.children && item.children.length > 0);
        if (hasMega || hasChildren) {
          const key = item.label;
          const isOpen = navOpen && openKey.value === key;
          return (
            <div
              key={key}
              class={`header-nav-dropdown${hasMega ? " header-nav-dropdown--mega" : ""}${
                isOpen ? " header-nav-dropdown--open" : ""
              }`}
            >
              <button
                type="button"
                class={`${linkClass} header-nav-dropdown__trigger`}
                aria-haspopup={hasMega ? "true" : "menu"}
                aria-expanded={isOpen}
                onClick$={() => {
                  if (headerMenu.openId === "nav" && openKey.value === key) {
                    openKey.value = null;
                    closeHeaderDropdown(headerMenu, "nav");
                    return;
                  }
                  openKey.value = key;
                  headerMenu.openId = "nav";
                }}
              >
                <span>{item.label}</span>
                <span class="header-nav-dropdown__caret" aria-hidden="true" />
              </button>
              {isOpen && hasMega && item.mega ? (
                <div class="header-nav-mega" role="menu">
                  {item.mega.columns.map((col) => (
                    <div key={col.title || col.links[0]?.label} class="header-nav-mega__col">
                      {col.title ? (
                        <p class="header-nav-mega__title">{col.title}</p>
                      ) : null}
                      <ul class="header-nav-mega__list">
                        {col.links.map((child) => {
                          const nested = child.children && child.children.length > 0;
                          if (nested) {
                            return (
                              <li key={childKey(child)} class="header-nav-mega__group" role="none">
                                <span class="header-nav-mega__group-title">{child.label}</span>
                                <ul class="header-nav-mega__sublist">
                                  {child.children!.map((sub) => (
                                    <li key={childKey(sub)} role="none">
                                      {sub.disabled || !sub.href ? (
                                        <span class="header-nav-dropdown__option header-nav-dropdown__option--disabled">
                                          {sub.label}
                                        </span>
                                      ) : (
                                        <button
                                          type="button"
                                          class="header-nav-dropdown__option"
                                          role="menuitem"
                                          onClick$={() => go$(sub.href!)}
                                        >
                                          {sub.label}
                                        </button>
                                      )}
                                    </li>
                                  ))}
                                </ul>
                              </li>
                            );
                          }
                          return (
                          <li key={childKey(child)} role="none">
                            {child.action === "open-support-chat" ? (
                              <button
                                type="button"
                                class="header-nav-dropdown__option"
                                role="menuitem"
                                onClick$={() => {
                                  openKey.value = null;
                                  closeHeaderDropdown(headerMenu, "nav");
                                  openSupportChatEvent();
                                }}
                              >
                                {child.label}
                              </button>
                            ) : child.disabled || !child.href ? (
                              <span class="header-nav-dropdown__option header-nav-dropdown__option--disabled">
                                {child.label}
                                {child.hint ? ` (${child.hint})` : ""}
                              </span>
                            ) : child.href.startsWith("tel:") ? (
                              <a
                                href={child.href}
                                class="header-nav-dropdown__option"
                                role="menuitem"
                              >
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
                      </ul>
                    </div>
                  ))}
                </div>
              ) : null}
              {isOpen && !hasMega && item.children ? (
                <ul class="header-nav-dropdown__menu" role="menu">
                  {item.children.map((child) => (
                    <li key={childKey(child)} role="none">
                      {child.action === "open-support-chat" ? (
                        <button
                          type="button"
                          class="header-nav-dropdown__option"
                          role="menuitem"
                          onClick$={() => {
                            openKey.value = null;
                            closeHeaderDropdown(headerMenu, "nav");
                            openSupportChatEvent();
                          }}
                        >
                          {child.label}
                        </button>
                      ) : child.disabled || !child.href ? (
                        <span class="header-nav-dropdown__option header-nav-dropdown__option--disabled">
                          {child.label}
                          {child.hint ? ` (${child.hint})` : ""}
                        </span>
                      ) : child.href.startsWith("tel:") ? (
                        <a
                          href={child.href}
                          class="header-nav-dropdown__option"
                          role="menuitem"
                        >
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
                  ))}
                </ul>
              ) : null}
            </div>
          );
        }

        if (item.external && item.href) {
          return (
            <a
              key={item.href}
              href={item.href}
              class={linkClass}
              target="_blank"
              rel="noopener noreferrer"
            >
              {item.label}
            </a>
          );
        }

        if (!item.href) {
          return null;
        }

        return (
          <Link key={item.href} href={item.href} class={linkClass} prefetch={false}>
            {item.label}
          </Link>
        );
      })}
    </>
  );
});

interface FaqItem {
  question: string;
  answer: string;
}

interface FaqAccordionProps {
  items: FaqItem[];
  openIndex: number | null;
  onToggle$: QRL<(index: number) => void>;
}

export const FaqAccordion = component$<FaqAccordionProps>(
  ({ items, openIndex, onToggle$ }) => {
    return (
      <div class="faq-list">
        {items.map((item, index) => {
          const open = openIndex === index;
          return (
            <div key={item.question} class={`faq-item${open ? " faq-item--open" : ""}`}>
              <button
                type="button"
                class="faq-question"
                aria-expanded={open}
                onClick$={() => onToggle$(index)}
              >
                <span>{item.question}</span>
                <span class="faq-toggle" aria-hidden="true">
                  {open ? "-" : "+"}
                </span>
              </button>
              {open ? (
                <div class="faq-answer">
                  <p>{item.answer}</p>
                </div>
              ) : null}
            </div>
          );
        })}
      </div>
    );
  },
);
