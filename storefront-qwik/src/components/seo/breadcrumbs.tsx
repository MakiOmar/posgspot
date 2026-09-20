import { component$ } from "@builder.io/qwik";
import { PageTitleBar, type PageTitleCrumb } from "~/components/layout/page-title-bar";

export interface BreadcrumbItem {
  label: string;
  /** Locale-relative path (e.g. `/products`) or omit for the current page. */
  href?: string;
}

interface BreadcrumbsProps {
  /** Page H1 shown in the fancy title bar. */
  title: string;
  /**
   * Trail after Home. Do not include Home — PageTitleBar adds it.
   * The last item is usually omitted when it matches `title`.
   */
  items?: BreadcrumbItem[];
}

/**
 * Fancy page title bar with breadcrumb trail (pair with BreadcrumbList JSON-LD).
 * Prefer this over the legacy `.content-breadcrumb` strip.
 */
export const Breadcrumbs = component$<BreadcrumbsProps>(({ title, items }) => {
  const crumbs: PageTitleCrumb[] = (items ?? [])
    .filter((item) => item.href || item.label !== title)
    .map((item) => ({
      label: item.label,
      href: item.href,
    }));

  return <PageTitleBar title={title} crumbs={crumbs} />;
});
