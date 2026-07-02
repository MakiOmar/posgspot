import { component$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import type { LegalDocument } from "~/lib/legal-content";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";

interface LegalDocumentViewProps {
  doc: LegalDocument;
}

/** Renders a policy / terms page with breadcrumb and sections. */
export const LegalDocumentView = component$<LegalDocumentViewProps>(({ doc }) => {
  const { locale } = useI18n();

  return (
    <article class="content-page legal-page">
      <nav class="content-breadcrumb" aria-label="Breadcrumb">
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{doc.breadcrumbLabel}</span>
      </nav>

      <h1 class="content-title">{doc.title}</h1>

      {doc.lastUpdated ? (
        <p class="legal-meta footer-muted">Last updated: {doc.lastUpdated}</p>
      ) : null}

      {doc.intro ? <p class="content-prose legal-intro">{doc.intro}</p> : null}

      <div class="legal-sections">
        {doc.sections.map((section) => (
          <section key={section.title} class="legal-section">
            <h2 class="legal-section__title">{section.title}</h2>
            {section.paragraphs?.map((paragraph) => (
              <p key={paragraph} class="content-prose">
                {paragraph}
              </p>
            ))}
            {section.list?.length ? (
              <ul class="legal-list">
                {section.list.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            ) : null}
          </section>
        ))}
      </div>

      {doc.footerNote ? <p class="content-prose legal-footer-note">{doc.footerNote}</p> : null}
    </article>
  );
});
