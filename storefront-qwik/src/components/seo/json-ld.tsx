import { component$ } from "@builder.io/qwik";
import { useServerData } from "@builder.io/qwik";
import { serializeJsonLd } from "~/lib/security/json-ld";

interface JsonLdProps {
  data: Record<string, unknown>;
}

/** Renders structured data for SEO. */
export const JsonLd = component$<JsonLdProps>(({ data }) => {
  const nonce = useServerData<string>("nonce");

  return (
    <script
      type="application/ld+json"
      {...(nonce ? { nonce } : {})}
      dangerouslySetInnerHTML={serializeJsonLd(data)}
    />
  );
});
