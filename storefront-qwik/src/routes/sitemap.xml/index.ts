import type { RequestHandler } from "@builder.io/qwik-city";
import { fetchBrands, fetchCategories, fetchProductsPage } from "~/lib/api";
import { ROBOTS_DISALLOW_ALL } from "~/lib/config";
import { DEFAULT_CONTENT_LOCALE, STORE_LOCALES } from "~/lib/i18n/config";
import { localePath } from "~/lib/i18n/paths";
import { buildSitemapXml, staticSitemapPaths } from "~/lib/seo-files";
import { cachedSsr } from "~/lib/ssr-shell-cache";
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

const SITEMAP_TTL_MS = 60 * 60_000;

/** True when at least one locale contributed catalog URLs (otherwise POS was likely unreachable). */
type SitemapBuild = { paths: string[]; complete: boolean };

export const onGet: RequestHandler = async ({ url, headers, send }) => {
  headers.set("Content-Type", "application/xml; charset=utf-8");
  headers.set("Cache-Control", "public, max-age=3600");

  // Staging / pre-launch: do not advertise URLs to crawlers.
  if (ROBOTS_DISALLOW_ALL) {
    send(200, buildSitemapXml(url.origin, []));
    return;
  }

  const { paths } = await cachedSsr<SitemapBuild>("sitemap", collectSitemapPaths, {
    ttlMs: SITEMAP_TTL_MS,
    isDegraded: (build) => !build.complete,
  });
  send(200, buildSitemapXml(url.origin, paths));
};

/** Walks categories, brands and up to 50 product pages per locale (dozens of POS calls). */
async function collectSitemapPaths(): Promise<SitemapBuild> {
  const paths = new Set<string>(staticSitemapPaths());
  let complete = true;

  for (const loc of STORE_LOCALES) {
    try {
      const { data: categories } = await fetchCategories(loc.code);
      for (const path of collectCategoryPaths(categories, loc.code)) {
        paths.add(path);
      }

      try {
        const { data: brands } = await fetchBrands(loc.code);
        for (const brand of brands) {
          if (brand.slug) {
            paths.add(localePath(loc.code, `/brands/${encodeURIComponent(brand.slug)}`));
          }
        }
      } catch {
        // Brand paths skipped when brands API unreachable.
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
      // Locale-specific paths skipped when API unreachable; not cached so the next request retries.
      complete = false;
    }
  }

  // Ensure default locale home is always present.
  paths.add(localePath(DEFAULT_CONTENT_LOCALE, "/"));

  return { paths: [...paths], complete };
}
