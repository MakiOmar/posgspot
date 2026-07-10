import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { fetchBrands } from "~/lib/api";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import type { Brand } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useBrandsPage = routeLoader$(async ({ params }): Promise<Brand[]> => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchBrands(locale);
    return data;
  } catch {
    return [];
  }
});

export default component$(() => {
  const brands = useBrandsPage();
  const lang = useLangParam();

  return (
    <section>
      <h1 class="page-title">{tStatic(lang.value, "brands.title")}</h1>
      <p class="footer-muted" style={{ marginBottom: "1.5rem" }}>
        {tStatic(lang.value, "brands.lead")}
      </p>

      {brands.value.length === 0 ? (
        <div class="empty-state">{tStatic(lang.value, "catalog.noBrands")}</div>
      ) : (
        <ul class="brands-index">
          {brands.value.map((brand) => (
            <li key={brand.id}>
              <Link href={localePath(lang.value, `/brands/${encodeURIComponent(brand.slug)}`)}>
                {brand.name}
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const title = tStatic(lang, "seo.brandsTitle", { businessName: settings.business_name });
  const description = tStatic(lang, "seo.brandsDescription", {
    businessName: settings.business_name,
  });

  return {
    title,
    meta: [
      { name: "description", content: description },
      { property: "og:title", content: title },
      { property: "og:description", content: description },
      { property: "og:type", content: "website" },
      { name: "twitter:card", content: "summary" },
    ],
    links: publicSeoLinks(url.origin, "/brands", lang),
  };
};
