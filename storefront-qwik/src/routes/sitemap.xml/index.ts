import type { RequestHandler } from "@builder.io/qwik-city";
import { fetchCategories, fetchProductsPage } from "~/lib/api";
import { DEFAULT_CONTENT_LOCALE, STORE_LOCALES } from "~/lib/i18n/config";
import { localePath } from "~/lib/i18n/paths";
import { buildSitemapXml, staticSitemapPaths } from "~/lib/seo-files";
import type { Category } from "~/lib/types";

function collectCategoryPaths(categories: Category[], locale: string): string[] {
  const paths: string[] = [];
  for (const category of categories) {
    if (category.slug) {
      paths.push(localePath(locale, `/category/${encodeURIComponent(category.slug)}`));
    }
    if (category.sub_categories?.length) {
      paths.push(...collectCategoryPaths(category.sub_categories, locale));
    }
  }
  return paths;
}

export const onGet: RequestHandler = async ({ url, headers, send }) => {
  const paths = new Set<string>(staticSitemapPaths());

  for (const loc of STORE_LOCALES) {
    try {
      const { data: categories } = await fetchCategories(loc.code);
      for (const path of collectCategoryPaths(categories, loc.code)) {
        paths.add(path);
      }

      let page = 1;
      let lastPage = 1;
      do {
        const result = await fetchProductsPage({ per_page: 100, page }, loc.code);
        lastPage = Number(result.meta.last_page ?? 1);
        for (const product of result.data) {
          const segment = product.slug || String(product.id);
          paths.add(localePath(loc.code, `/products/${encodeURIComponent(segment)}`));
        }
        page += 1;
      } while (page <= lastPage && page <= 50);
    } catch {
      // Locale-specific paths skipped when API unreachable.
    }
  }

  // Ensure default locale home is always present.
  paths.add(localePath(DEFAULT_CONTENT_LOCALE, "/"));

  headers.set("Content-Type", "application/xml; charset=utf-8");
  send(200, buildSitemapXml(url.origin, [...paths]));
};
