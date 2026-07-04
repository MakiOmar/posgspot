import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";

export interface BreadcrumbItem {
  label: string;
  /** Omit href for the current page crumb. */
  href?: string;
}

interface BreadcrumbsProps {
  items: BreadcrumbItem[];
}

/** Visible breadcrumb trail (pair with BreadcrumbList JSON-LD on the page). */
export const Breadcrumbs = component$<BreadcrumbsProps>(({ items }) => {
  const { locale } = useI18n();

  if (items.length === 0) {
    return null;
  }

  return (
    <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
      {items.flatMap((item, index) => {
        const nodes = [];
        if (index > 0) {
          nodes.push(
            <span key={`sep-${index}`} aria-hidden="true">
              ›
            </span>,
          );
        }
        if (item.href) {
          nodes.push(
            <Link key={`link-${index}`} href={item.href}>
              {item.label}
            </Link>,
          );
        } else {
          nodes.push(<span key={`text-${index}`}>{item.label}</span>);
        }
        return nodes;
      })}
    </nav>
  );
});
