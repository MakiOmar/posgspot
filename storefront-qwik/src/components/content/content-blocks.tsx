import { $, component$, useOnDocument, useSignal, type QRL } from "@builder.io/qwik";
import { Link, useNavigate } from "@builder.io/qwik-city";
import {
  closeHeaderDropdown,
  useHeaderDropdown,
} from "~/lib/header-dropdown-context";
import type { ResolvedNavItem } from "~/lib/header-nav";
import { openSupportChatEvent } from "~/lib/support-chat";

interface HeaderNavItemsProps {
  links: ResolvedNavItem[];
  linkClass: string;
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
        if (item.children && item.children.length > 0) {
          const key = item.label;
          const isOpen = navOpen && openKey.value === key;
          return (
            <div
              key={key}
              class={`header-nav-dropdown${isOpen ? " header-nav-dropdown--open" : ""}`}
            >
              <button
                type="button"
                class={`${linkClass} header-nav-dropdown__trigger`}
                aria-haspopup="menu"
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
              {isOpen ? (
                <ul class="header-nav-dropdown__menu" role="menu">
                  {item.children.map((child) => (
                    <li key={child.href || child.action || child.label} role="none">
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
                  {open ? "−" : "+"}
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
