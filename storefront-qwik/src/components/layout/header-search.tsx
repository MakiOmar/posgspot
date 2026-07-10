import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation, useNavigate } from "@builder.io/qwik-city";
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
  // -1 = no highlight (Enter → full search); arrows / hover select a product.
  const activeIndex = useSignal(-1);

  // Keep the input in sync when navigating (e.g. after landing on /search?q=).
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
      activeIndex.value = -1;
      return;
    }

    loading.value = true;
    const timer = setTimeout(async () => {
      try {
        const { data } = await searchProducts(term, 8, locale);
        results.value = data;
        open.value = true;
        activeIndex.value = -1;
      } catch {
        results.value = [];
        activeIndex.value = -1;
      } finally {
        loading.value = false;
      }
    }, 280);

    cleanup(() => clearTimeout(timer));
  });

  const submitSearch$ = $(async (term: string) => {
    const base = localePath(locale, "/search");
    const href = term ? `${base}?q=${encodeURIComponent(term)}` : base;
    open.value = false;
    activeIndex.value = -1;
    results.value = [];
    await withPendingFeedback(pending, searching, async () => {
      await nav(href);
    });
  });

  const goToProduct$ = $(async (productId: number) => {
    const product = results.value.find((item) => item.id === productId);
    if (!product) {
      return;
    }
    const href = productPath(product, locale);
    open.value = false;
    activeIndex.value = -1;
    results.value = [];
    await nav(href);
  });

  return (
    <div class="header-search-wrap">
      <form
        class="header-search"
        role="search"
        preventdefault:submit
        onSubmit$={async (_, formEl) => {
          if (open.value && activeIndex.value >= 0) {
            const item = results.value[activeIndex.value];
            if (item) {
              await goToProduct$(item.id);
              return;
            }
          }
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
          onKeyDown$={(event) => {
            if (!open.value || results.value.length === 0) {
              return;
            }
            if (event.key === "Escape") {
              event.preventDefault();
              open.value = false;
              activeIndex.value = -1;
              return;
            }
            if (event.key === "ArrowDown") {
              event.preventDefault();
              activeIndex.value =
                activeIndex.value < results.value.length - 1 ? activeIndex.value + 1 : 0;
              return;
            }
            if (event.key === "ArrowUp") {
              event.preventDefault();
              activeIndex.value =
                activeIndex.value <= 0 ? results.value.length - 1 : activeIndex.value - 1;
              return;
            }
            if (event.key === "Enter" && activeIndex.value >= 0) {
              event.preventDefault();
              const item = results.value[activeIndex.value];
              if (item) {
                void goToProduct$(item.id);
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
        >
          {loading.value ? (
            <p class="header-search-suggestions__status">{tStatic(locale, "common.searching")}</p>
          ) : null}

          {!loading.value && results.value.length === 0 ? (
            <p class="header-search-suggestions__status">{tStatic(locale, "common.noResults")}</p>
          ) : null}

          {!loading.value
            ? results.value.map((product, index) => (
                <button
                  key={product.id}
                  type="button"
                  class={`header-search-suggestion${index === activeIndex.value ? " is-active" : ""}`}
                  role="option"
                  aria-selected={index === activeIndex.value}
                  // Prevent input blur so the panel stays open through the click.
                  onMouseDown$={(event) => event.preventDefault()}
                  onMouseEnter$={() => {
                    activeIndex.value = index;
                  }}
                  onClick$={async (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    await goToProduct$(product.id);
                  }}
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
                </button>
              ))
            : null}

          {!loading.value && results.value.length > 0 ? (
            <button
              type="button"
              class="header-search-suggestions__all"
              onMouseDown$={(event) => event.preventDefault()}
              onClick$={async (event) => {
                event.preventDefault();
                event.stopPropagation();
                await submitSearch$(query.value.trim());
              }}
            >
              {tStatic(locale, "common.viewAllResults", { query: query.value.trim() })}
            </button>
          ) : null}
        </div>
      ) : null}
    </div>
  );
});
