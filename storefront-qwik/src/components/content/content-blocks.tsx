import { $, component$, useOnDocument, useSignal, type QRL } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import type { ResolvedNavItem } from "~/lib/header-nav";

interface HeaderNavItemsProps {
  links: ResolvedNavItem[];
  linkClass: string;
}

export const HeaderNavItems = component$<HeaderNavItemsProps>(({ links, linkClass }) => {
  const openKey = useSignal<string | null>(null);

  const close$ = $(() => {
    openKey.value = null;
  });

  useOnDocument(
    "click",
    $((event) => {
      const target = event.target as HTMLElement | null;
      if (!target?.closest(".header-nav-dropdown")) {
        openKey.value = null;
      }
    }),
  );

  useOnDocument(
    "keydown",
    $((event) => {
      if ((event as KeyboardEvent).key === "Escape") {
        openKey.value = null;
      }
    }),
  );

  return (
    <>
      {links.map((item) => {
        if (item.children && item.children.length > 0) {
          const key = item.label;
          const isOpen = openKey.value === key;
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
                  openKey.value = openKey.value === key ? null : key;
                }}
              >
                <span>{item.label}</span>
                <span class="header-nav-dropdown__caret" aria-hidden="true" />
              </button>
              {isOpen ? (
                <ul class="header-nav-dropdown__menu" role="menu">
                  {item.children.map((child) => (
                    <li key={child.href} role="none">
                      <Link
                        href={child.href}
                        class="header-nav-dropdown__option"
                        role="menuitem"
                        onClick$={close$}
                      >
                        {child.label}
                      </Link>
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
          <Link key={item.href} href={item.href} class={linkClass}>
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
