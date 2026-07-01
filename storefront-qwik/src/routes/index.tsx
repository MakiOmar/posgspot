import { component$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { JsonLd } from "~/components/seo/json-ld";
import { fetchProductsPage } from "~/lib/api";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { useSiteSettings } from "~/routes/layout";

export const useHomeProducts = routeLoader$(async () => {
  try {
    return await fetchProductsPage({ per_page: 8, in_stock_only: true });
  } catch {
    return { data: [], meta: { current_page: 1, last_page: 1, per_page: 8, total: 0 } };
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const products = useHomeProducts();

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "WebSite",
          name: settings.value.business_name,
          url: typeof window !== "undefined" ? window.location.origin : undefined,
          potentialAction: {
            "@type": "SearchAction",
            target: "/products?q={search_term_string}",
            "query-input": "required name=search_term_string",
          },
        }}
      />

      <section>
        <h1 class="page-title">Welcome to {settings.value.business_name}</h1>
        <p style={{ color: "var(--gs-muted)", marginBottom: "2rem" }}>
          Consoles, accessories, and gaming services — shop online or check store availability.
        </p>

        <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "1rem" }}>
          <h2 style={{ margin: 0, fontSize: "1.25rem" }}>In stock now</h2>
          <a href="/products">View all →</a>
        </div>

        {products.value.data.length === 0 ? (
          <div class="empty-state">No products available online right now.</div>
        ) : (
          <div class="product-grid">
            {products.value.data.map((product) => (
              <ProductCard key={product.id} product={product} settings={settings.value} />
            ))}
          </div>
        )}
      </section>
    </>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const title = `${settings.business_name} — Gaming Store`;
  const description = `Shop consoles, accessories, and services at ${settings.business_name}.`;

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { name: "twitter:card", content: "summary_large_image" },
      ],
    },
    settings,
  );
};
