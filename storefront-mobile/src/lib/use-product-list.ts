import { useCallback, useEffect, useState } from "react";
import { fetchProducts, searchProducts } from "./api";
import type { ContentLocale, ProductSummary } from "./types";
import {
  sortToApi,
  type ProductSort,
} from "../components/catalog/ProductListToolbar";

type Options = {
  locale: ContentLocale;
  mode?: "products" | "search";
  searchQ?: string;
  categorySlug?: string;
  brandSlug?: string;
  featured?: boolean;
  pageSize?: number;
};

export function useProductList({
  locale,
  mode = "products",
  searchQ = "",
  categorySlug,
  brandSlug,
  featured,
  pageSize = 24,
}: Options) {
  const [sort, setSortState] = useState<ProductSort>("newest");
  const [inStockOnly, setInStockState] = useState(false);
  const [page, setPage] = useState(1);
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);

  const loadPage = useCallback(
    async (pageNum: number, append: boolean, sortVal: ProductSort, stockOnly: boolean) => {
      if (mode === "search" && searchQ.trim().length < 2) {
        setProducts([]);
        setHasMore(false);
        setLoading(false);
        return;
      }
      if (append) setLoadingMore(true);
      else setLoading(true);
      setError(null);
      try {
        if (mode === "search") {
          const { data } = await searchProducts(searchQ.trim(), locale);
          let list = data || [];
          if (stockOnly) list = list.filter((p) => p.in_stock !== false);
          setProducts(list);
          setHasMore(false);
        } else {
          const { data, meta } = await fetchProducts(
            {
              per_page: pageSize,
              page: pageNum,
              sort: sortToApi(sortVal),
              in_stock: stockOnly ? 1 : undefined,
              category_slug: categorySlug,
              brand_slug: brandSlug,
              featured: featured ? 1 : undefined,
            },
            locale,
          );
          const list = data || [];
          setProducts((prev) => (append ? [...prev, ...list] : list));
          const last = Number(meta.last_page ?? 1);
          const current = Number(meta.current_page ?? pageNum);
          setHasMore(current < last && list.length > 0);
        }
      } catch (e) {
        setError(e instanceof Error ? e.message : "error");
        if (!append) setProducts([]);
      } finally {
        setLoading(false);
        setLoadingMore(false);
      }
    },
    [locale, mode, searchQ, pageSize, categorySlug, brandSlug, featured],
  );

  useEffect(() => {
    setPage(1);
    const timer = setTimeout(
      () => void loadPage(1, false, sort, inStockOnly),
      mode === "search" ? 250 : 0,
    );
    return () => clearTimeout(timer);
  }, [loadPage, mode, sort, inStockOnly]);

  return {
    sort,
    setSort: (s: ProductSort) => {
      setSortState(s);
      setPage(1);
    },
    inStockOnly,
    setInStockOnly: (v: boolean) => {
      setInStockState(v);
      setPage(1);
    },
    products,
    loading,
    loadingMore,
    error,
    hasMore,
    loadMore: () => {
      if (!hasMore || loadingMore || loading) return;
      const next = page + 1;
      setPage(next);
      void loadPage(next, true, sort, inStockOnly);
    },
    reload: () => {
      setPage(1);
      void loadPage(1, false, sort, inStockOnly);
    },
  };
}
