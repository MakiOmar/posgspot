import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, useLocation, useNavigate } from "@builder.io/qwik-city";
import { SearchIcon } from "~/components/icons";
import { searchProducts } from "~/lib/api";
import { productPath, formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import type { ProductSummary, StoreSettings } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";

interface HeaderSearchProps {
  settings: StoreSettings;
}

export const HeaderSearch = component$<HeaderSearchProps>(({ settings }) => {
  const loc = useLocation();
  const nav = useNavigate();
  const pending = usePendingState();
  const { locale } = useI18n();
  const searching = useSignal(false);
  const query = useSignal(loc.url.searchParams.get("q") || "");
  const results = useSignal<ProductSummary[]>([]);
  const open = useSignal(false);
  const loading = useSignal(false);
  const activeIndex = useSignal(-1);

  // Keep the input in sync when navigating (e.g. after landing on /products?q=).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => loc.url.pathname);
    const urlQ = track(() => loc.url.searchParams.get("q") || "");
    const active = document.activeElement?.closest(".header-search-wrap");
    if (!open.value && !active) {
      query.value = urlQ;
    }
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track, cleanup }) => {
    track(() => query.value);
    const term = query.value.trim();

    if (term.length < 2) {
      results.value = [];
      loading.value = false;
      return;
    }

    loading.value = true;
    const timer = setTimeout(async () => {
      try {
        const { data } = await searchProducts(term, 8, locale);
        results.value = data;
        open.value = true;
        activeIndex.value = data.length > 0 ? 0 : -1;
      } catch {
        results.value = [];
      } finally {
        loading.value = false;
      }
    }, 280);

    cleanup(() => clearTimeout(timer));
  });

  const submitSearch$ = $(async (term: string) => {
    const base = localePath(locale, "/products");
    const href = term ? `${base}?q=${encodeURIComponent(term)}` : base;
    open.value = false;
    await withPendingFeedback(pending, searching, async () => {
      await nav(href);
    });
  });

  const close$ = $(() => {
    open.value = false;
    activeIndex.value = -1;
  });

  return (
    <div class="header-search-wrap">
      <form
        class="header-search"
        role="search"
        preventdefault:submit
        onSubmit$={async (_, formEl) => {
          const form = formEl as HTMLFormElement;
          const q = new FormData(form).get("q");
          const term = typeof q === "string" ? q.trim() : "";
          await submitSearch$(term);
        }}
      >
        <input
          type="search"
          name="q"
          placeholder={tStatic(locale, "header.searchPlaceholder")}
          aria-label={tStatic(locale, "header.searchLabel")}
          aria-expanded={open.value}
          aria-controls="header-search-suggestions"
          aria-autocomplete="list"
          autoComplete="off"
          value={query.value}
          onInput$={(_, el) => {
            query.value = el.value;
            if (el.value.trim().length < 2) {
              open.value = false;
            }
          }}
          onFocus$={() => {
            if (results.value.length > 0 && query.value.trim().length >= 2) {
              open.value = true;
            }
          }}
          onKeyDown$={async (event) => {
            if (!open.value || results.value.length === 0) {
              return;
            }
            if (event.key === "Escape") {
              await close$();
              return;
            }
            if (event.key === "ArrowDown") {
              event.preventDefault();
              activeIndex.value = Math.min(activeIndex.value + 1, results.value.length - 1);
              return;
            }
            if (event.key === "ArrowUp") {
              event.preventDefault();
              activeIndex.value = Math.max(activeIndex.value - 1, 0);
              return;
            }
            if (event.key === "Enter" && activeIndex.value >= 0) {
              event.preventDefault();
              const item = results.value[activeIndex.value];
              if (item) {
                open.value = false;
                await nav(productPath(item, locale));
              }
            }
          }}
        />
        <button type="submit" aria-label={tStatic(locale, "header.search")} disabled={searching.value}>
          <SearchIcon size={18} />
        </button>
      </form>

      {open.value && query.value.trim().length >= 2 ? (
        <div
          id="header-search-suggestions"
          class="header-search-suggestions"
          role="listbox"
          aria-label={tStatic(locale, "header.searchSuggestions")}
          onMouseDown$={(event) => event.preventDefault()}
        >
          {loading.value ? (
            <p class="header-search-suggestions__status">{tStatic(locale, "common.searching")}</p>
          ) : null}

          {!loading.value && results.value.length === 0 ? (
            <p class="header-search-suggestions__status">{tStatic(locale, "common.noResults")}</p>
          ) : null}

          {!loading.value
            ? results.value.map((product, index) => (
                <Link
                  key={product.id}
                  href={productPath(product, locale)}
                  class={`header-search-suggestion${index === activeIndex.value ? " is-active" : ""}`}
                  role="option"
                  aria-selected={index === activeIndex.value}
                  onClick$={close$}
                >
                  {product.image_url ? (
                    <img
                      class="header-search-suggestion__thumb"
                      src={product.image_url}
                      alt=""
                      width={40}
                      height={40}
                      loading="lazy"
                    />
                  ) : (
                    <span class="header-search-suggestion__thumb" aria-hidden="true" />
                  )}
                  <span class="header-search-suggestion__body">
                    <span class="header-search-suggestion__name">{product.name}</span>
                    <span class="header-search-suggestion__price">
                      {formatPrice(product.price, settings.currency, locale)}
                    </span>
                  </span>
                </Link>
              ))
            : null}

          {!loading.value && results.value.length > 0 ? (
            <button
              type="button"
              class="header-search-suggestions__all"
              onClick$={() => submitSearch$(query.value.trim())}
            >
              {tStatic(locale, "common.viewAllResults", { query: query.value.trim() })}
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
});
