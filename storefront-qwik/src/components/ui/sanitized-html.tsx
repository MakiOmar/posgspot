import { component$ } from "@builder.io/qwik";
import { sanitizeProductHtml } from "~/lib/sanitize-html";

interface SanitizedHtmlProps {
  html: string | null | undefined;
  class?: string;
}

/** Renders admin-provided HTML after stripping scripts and dangerous attributes. */
export const SanitizedHtml = component$<SanitizedHtmlProps>(({ html, class: className }) => {
  const safe = sanitizeProductHtml(html);
  if (!safe) {
    return null;
  }

  return <div class={className} dangerouslySetInnerHTML={safe} />;
});
