import { component$, type QRL } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import type { HeaderNavLink } from "~/lib/header-nav";

interface HeaderNavItemsProps {
  links: HeaderNavLink[];
  linkClass: string;
}

export const HeaderNavItems = component$<HeaderNavItemsProps>(({ links, linkClass }) => {
  return (
    <>
      {links.map((item) =>
        item.external ? (
          <a
            key={item.href}
            href={item.href}
            class={linkClass}
            target="_blank"
            rel="noopener noreferrer"
          >
            {item.label}
          </a>
        ) : (
          <Link key={item.href} href={item.href} class={linkClass}>
            {item.label}
          </Link>
        ),
      )}
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
