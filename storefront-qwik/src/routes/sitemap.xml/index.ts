import type { RequestHandler } from "@builder.io/qwik-city";
import { fetchCategories, fetchProductsPage } from "~/lib/api";
import { buildSitemapXml, staticSitemapPaths } from "~/lib/seo-files";
import type { Category } from "~/lib/types";

function collectCategoryPaths(categories: Category[]): string[] {
  const paths: string[] = [];
  for (const category of categories) {
    if (category.slug) {
      paths.push(`/category/${encodeURIComponent(category.slug)}`);
    }
    if (category.sub_categories?.length) {
      paths.push(...collectCategoryPaths(category.sub_categories));
    }
  }
  return paths;
}

export const onGet: RequestHandler = async ({ url, headers, send }) => {
  const paths = new Set<string>(staticSitemapPaths());

  try {
    const { data: categories } = await fetchCategories();
    for (const path of collectCategoryPaths(categories)) {
      paths.add(path);
    }

    let page = 1;
    let lastPage = 1;
    do {
      const result = await fetchProductsPage({ per_page: 100, page });
      lastPage = Number(result.meta.last_page ?? 1);
      for (const product of result.data) {
        const segment = product.slug || String(product.id);
        paths.add(`/products/${encodeURIComponent(segment)}`);
      }
      page += 1;
    } while (page <= lastPage && page <= 50);
  } catch {
    // Static paths only when API is unreachable during sitemap generation.
  }

  headers.set("Content-Type", "application/xml; charset=utf-8");
  send(200, buildSitemapXml(url.origin, [...paths]));
};
