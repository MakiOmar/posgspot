import { component$ } from "@builder.io/qwik";

interface JsonLdProps {
  data: Record<string, unknown>;
}

/** Renders structured data for SEO. */
export const JsonLd = component$<JsonLdProps>(({ data }) => {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={JSON.stringify(data)}
    />
  );
});
