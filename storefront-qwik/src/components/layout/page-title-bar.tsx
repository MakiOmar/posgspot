import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { HomeIcon } from "~/components/icons";
import { PageTitleBarParticles } from "~/components/layout/page-title-bar-particles";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";

export type PageTitleCrumb = {
  label: string;
  href?: string;
};

type Props = {
  title: string;
  crumbs?: PageTitleCrumb[];
};

/**
 * Fancy full-bleed page title bar: breadcrumbs + title + energy particles.
 * Lead, tabs, and other page chrome belong in the content section below.
 */
export const PageTitleBar = component$<Props>((props) => {
  const { locale } = useI18n();
  const crumbs = props.crumbs ?? [];

  return (
    <header class="page-title-bar">
      <PageTitleBarParticles />
      <div class="page-title-bar__inner">
        {crumbs.length > 0 ? (
          <nav class="page-title-bar__crumbs" aria-label={tStatic(locale, "a11y.breadcrumb")}>
            <Link href={localePath(locale, "/")} class="page-title-bar__home">
              <HomeIcon size={14} />
              <span>{tStatic(locale, "nav.home")}</span>
            </Link>
            {crumbs.map((crumb, index) => (
              <span key={`${index}-${crumb.label}`} class="page-title-bar__crumb">
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
        <h1 class="page-title-bar__title">{props.title}</h1>
      </div>
    </header>
  );
});
