import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { ProductCard } from "~/components/catalog/product-card";
import { ProductListToolbar } from "~/components/catalog/product-list-toolbar";
import { ChevronLeftIcon, ChevronRightIcon, SearchIcon } from "~/components/icons";
import { fetchProductsPage } from "~/lib/api";
import { parseProductListFilters } from "~/lib/catalog-filters";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useSearchResults = routeLoader$(async ({ query, params }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const filters = parseProductListFilters(query);
  const q = filters.q.trim();

  if (q.length < 1) {
    return {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      emptyQuery: true as const,
    };
  }

  try {
    const page = await fetchProductsPage(
      {
        page: filters.page,
        per_page: 20,
        q,
        in_stock_only: filters.inStockOnly,
        sort: filters.sort,
      },
      locale,
    );
    return { ...page, emptyQuery: false as const };
  } catch {
    return {
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
      emptyQuery: false as const,
    };
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const list = useSearchResults();
  const loc = useLocation();
  const nav = useNavigate();
  const pending = usePendingState();
  const { locale } = useI18n();
  const filters = parseProductListFilters(loc.url.searchParams);
  const submitting = useSignal(false);
  const inputQ = useSignal(filters.q);
  const { meta } = list.value;
  const listPath = loc.url.pathname || localePath(locale, "/search");
  const listKey = loc.url.search || "?";

  // Keep the page search box aligned with the URL when filters/nav change.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    const urlQ = track(() => loc.url.searchParams.get("q") || "");
    inputQ.value = urlQ;
  });

  const buildPageUrl = (page: number) => {
    const params = new URLSearchParams(loc.url.searchParams);
    if (page <= 1) {
      params.delete("page");
    } else {
      params.set("page", String(page));
    }
    const qs = params.toString();
    return qs ? `${listPath}?${qs}` : listPath;
  };

  const submitSearch$ = $(async (term: string) => {
    const trimmed = term.trim();
    const href = trimmed
      ? `${localePath(locale, "/search")}?q=${encodeURIComponent(trimmed)}`
      : localePath(locale, "/search");
    await withPendingFeedback(pending, submitting, async () => {
      await nav(href);
    });
  });

  return (
    <section class="search-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "search.heading")}</span>
      </nav>

      <h1 class="page-title">{tStatic(locale, "search.heading")}</h1>

      <form
        class="search-page__form"
        role="search"
        preventdefault:submit
        onSubmit$={async (_, formEl) => {
          const form = formEl as HTMLFormElement;
          const q = new FormData(form).get("q");
          const term = typeof q === "string" ? q.trim() : "";
          await submitSearch$(term);
        }}
      >
        <label class="sr-only" for="search-page-q">
          {tStatic(locale, "header.searchLabel")}
        </label>
        <input
          id="search-page-q"
          type="search"
          name="q"
          value={inputQ.value}
          placeholder={tStatic(locale, "header.searchPlaceholder")}
          autocomplete="off"
          onInput$={(_, el) => {
            inputQ.value = el.value;
          }}
          disabled={submitting.value}
        />
        <button type="submit" class="btn btn-primary" disabled={submitting.value}>
          <SearchIcon size={18} />
          <span>{tStatic(locale, "header.search")}</span>
        </button>
      </form>

      {list.value.emptyQuery ? (
        <div class="empty-state" key="empty-query">
          <p>{tStatic(locale, "search.prompt")}</p>
          <Link href={localePath(locale, "/products")} class="link-accent">
            {tStatic(locale, "search.browseShop")}
          </Link>
        </div>
      ) : (
        <>
          <ProductListToolbar basePath={listPath} filters={filters} />

          {list.value.data.length === 0 ? (
            <div class="empty-state" key={listKey}>
              <p>{tStatic(locale, "search.noResultsFor", { query: filters.q })}</p>
              <Link href={localePath(locale, "/products")} class="link-accent">
                {tStatic(locale, "search.browseShop")}
              </Link>
            </div>
          ) : (
            <div key={listKey}>
              <p class="footer-muted" style={{ marginBottom: "1rem" }}>
                {tStatic(locale, "catalog.productCount", { count: meta.total })}
              </p>
              <div class="product-grid">
                {list.value.data.map((product) => (
                  <ProductCard key={product.id} product={product} settings={settings.value} />
                ))}
              </div>

              {meta.last_page > 1 ? (
                <nav class="pagination" aria-label={tStatic(locale, "a11y.pagination")}>
                  {meta.current_page > 1 ? (
                    <Link href={buildPageUrl(meta.current_page - 1)} class="footer-contact">
                      <ChevronLeftIcon size={16} />
                      {tStatic(locale, "common.prev")}
                    </Link>
                  ) : null}
                  <span class="active">
                    {tStatic(locale, "common.pageOf", {
                      current: meta.current_page,
                      last: meta.last_page,
                    })}
                  </span>
                  {meta.current_page < meta.last_page ? (
                    <Link href={buildPageUrl(meta.current_page + 1)} class="footer-contact">
                      {tStatic(locale, "common.next")}
                      <ChevronRightIcon size={16} />
                    </Link>
                  ) : null}
                </nav>
              ) : null}
            </div>
          )}
        </>
      )}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const q = (url.searchParams.get("q") || "").trim();
  const title = q
    ? tStatic(lang, "seo.searchTitle", { query: q, businessName: settings.business_name })
    : tStatic(lang, "seo.searchPageTitle", { businessName: settings.business_name });
  const description = q
    ? tStatic(lang, "seo.searchDescription", { query: q, businessName: settings.business_name })
    : tStatic(lang, "seo.searchPageDescription", { businessName: settings.business_name });

  const path = q ? `/search?q=${encodeURIComponent(q)}` : "/search";

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { name: "twitter:card", content: "summary" },
        { name: "robots", content: "noindex, follow" },
      ],
      links: publicSeoLinks(url.origin, path, lang),
    },
    settings,
  );
};
