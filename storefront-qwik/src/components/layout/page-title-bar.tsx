import { component$, Slot } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";

export type PageTitleCrumb = {
  label: string;
  href?: string;
};

type Props = {
  title: string;
  lead?: string;
  crumbs?: PageTitleCrumb[];
  /** Optional eyebrow above the title */
  eyebrow?: string;
};

/**
 * Fancy full-bleed page title bar for storefront content pages.
 * Skip on homepage, checkout, account hub, and pages with custom heroes (e.g. about).
 */
export const PageTitleBar = component$<Props>((props) => {
  const { locale } = useI18n();
  const crumbs = props.crumbs ?? [];

  return (
    <header class="page-title-bar">
      <div class="page-title-bar__inner">
        {crumbs.length > 0 ? (
          <nav class="page-title-bar__crumbs" aria-label={tStatic(locale, "a11y.breadcrumb")}>
            <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
            {crumbs.map((crumb) => (
              <span key={crumb.label} class="page-title-bar__crumb">
                <span aria-hidden="true">›</span>
                {crumb.href ? (
                  <Link href={localePath(locale, crumb.href)}>{crumb.label}</Link>
                ) : (
                  <span>{crumb.label}</span>
                )}
              </span>
            ))}
          </nav>
        ) : null}
        {props.eyebrow ? <p class="page-title-bar__eyebrow">{props.eyebrow}</p> : null}
        <h1 class="page-title-bar__title">{props.title}</h1>
        {props.lead ? <p class="page-title-bar__lead">{props.lead}</p> : null}
        <Slot />
      </div>
    </header>
  );
});
