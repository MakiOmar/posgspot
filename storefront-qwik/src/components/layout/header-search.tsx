import { $, component$, useOnDocument, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation, useNavigate } from "@builder.io/qwik-city";
import { SearchIcon } from "~/components/icons";
import { searchCatalog } from "~/lib/api";
import { parseCatalogSearchType } from "~/lib/catalog-filters";
import { formatPrice } from "~/lib/format";
import {
  closeHeaderDropdown,
  useHeaderDropdown,
} from "~/lib/header-dropdown-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import type { CatalogSearchType, SearchHit, StoreSettings } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";

interface HeaderSearchProps {
  settings: StoreSettings;
  /** `modal` = icon opens a dialog (header style one); default inline field. */
  variant?: "inline" | "modal";
}

function searchPlaceholderKey(type: CatalogSearchType): string {
  if (type === "games") {
    return "header.searchPlaceholderGames";
  }
  if (type === "gift_cards") {
    return "header.searchPlaceholderGiftCards";
  }
  return "header.searchPlaceholder";
}

function searchHref(locale: string, term: string, type: CatalogSearchType): string {
  const params = new URLSearchParams();
  if (term) {
    params.set("q", term);
  }
  if (type === "games" || type === "gift_cards") {
    params.set("type", type);
  }
  const qs = params.toString();
  const base = localePath(locale, "/search");
  return qs ? `${base}?${qs}` : base;
}

function hitHref(hit: SearchHit, locale: string): string {
  if (hit.href) {
    return localePath(locale, hit.href);
  }
  return localePath(locale, `/products/${hit.slug || hit.id}`);
}

export const HeaderSearch = component$<HeaderSearchProps>(({ settings, variant = "inline" }) => {
  const loc = useLocation();
  const nav = useNavigate();
  const pending = usePendingState();
  const { locale } = useI18n();
  const headerMenu = useHeaderDropdown();
  const searching = useSignal(false);
  const digitalEnabled = settings.digital?.enabled !== false;
  const searchType = useSignal<CatalogSearchType>(
    parseCatalogSearchType(loc.url.searchParams.get("type")),
  );
  const query = useSignal(loc.url.searchParams.get("q") || "");
  const results = useSignal<SearchHit[]>([]);
  const loading = useSignal(false);
  // -1 = no highlight (Enter → full search); arrows / hover select a hit.
  const activeIndex = useSignal(-1);
  const open = headerMenu.openId === "search";

  // Keep the input in sync when navigating (e.g. after landing on /search?q=).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => loc.url.pathname);
    const urlQ = track(() => loc.url.searchParams.get("q") || "");
    const urlType = track(() => loc.url.searchParams.get("type") || "");
    const active = document.activeElement?.closest(".header-search-wrap");
    if (headerMenu.openId !== "search" && !active) {
      query.value = urlQ;
      if (digitalEnabled) {
        searchType.value = parseCatalogSearchType(urlType);
      }
    }
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track, cleanup }) => {
    track(() => query.value);
    track(() => searchType.value);
    const term = query.value.trim();
    const type = searchType.value;

    if (term.length < 2) {
      results.value = [];
      loading.value = false;
      activeIndex.value = -1;
      return;
    }

    loading.value = true;
    const timer = setTimeout(async () => {
      try {
        const { data } = await searchCatalog(term, 8, type, locale);
        results.value = data;
        headerMenu.openId = "search";
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

  useOnDocument(
    "click",
    $((event) => {
      const target = event.target as HTMLElement | null;
      if (!target?.closest(".header-search-wrap")) {
        closeHeaderDropdown(headerMenu, "search");
      }
    }),
  );

  const submitSearch$ = $(async (term: string) => {
    const href = searchHref(locale, term, searchType.value);
    closeHeaderDropdown(headerMenu, "search");
    activeIndex.value = -1;
    results.value = [];
    await withPendingFeedback(pending, searching, async () => {
      await nav(href);
    });
  });

  const goToHit$ = $(async (index: number) => {
    const hit = results.value[index];
    if (!hit) {
      return;
    }
    const href = hitHref(hit, locale);
    closeHeaderDropdown(headerMenu, "search");
    activeIndex.value = -1;
    results.value = [];
    await nav(href);
  });

  const showPanel = open && query.value.trim().length >= 2;
  const activeOptionId =
    showPanel && activeIndex.value >= 0 && results.value[activeIndex.value]
      ? `header-search-option-${results.value[activeIndex.value].kind || "item"}-${results.value[activeIndex.value].id}`
      : undefined;
  const placeholder = tStatic(locale, searchPlaceholderKey(searchType.value));
  const isModal = variant === "modal";

  const closeModal$ = $(() => {
    headerMenu.searchModalOpen = false;
    closeHeaderDropdown(headerMenu, "search");
  });

  const formAndSuggestions = (
    <>
      <form
        class="header-search"
        role="search"
        preventdefault:submit
        onSubmit$={async (_, formEl) => {
          if (headerMenu.openId === "search" && activeIndex.value >= 0) {
            const item = results.value[activeIndex.value];
            if (item) {
              await goToHit$(activeIndex.value);
              if (isModal) headerMenu.searchModalOpen = false;
              return;
            }
          }
          const form = formEl as HTMLFormElement;
          const q = new FormData(form).get("q");
          const term = typeof q === "string" ? q.trim() : "";
          await submitSearch$(term);
          if (isModal) headerMenu.searchModalOpen = false;
        }}
      >
        {digitalEnabled ? (
          <select
            class="header-search-type"
            name="type"
            aria-label={tStatic(locale, "header.searchTypeLabel")}
            value={searchType.value}
            onChange$={(_, el) => {
              searchType.value = parseCatalogSearchType(el.value);
              results.value = [];
              activeIndex.value = -1;
            }}
            onFocus$={() => {
              if (!isModal) {
                closeHeaderDropdown(headerMenu, "search");
              }
            }}
          >
            <option value="products">{tStatic(locale, "header.searchTypeProducts")}</option>
            <option value="games">{tStatic(locale, "header.searchTypeGames")}</option>
            <option value="gift_cards">{tStatic(locale, "header.searchTypeGiftCards")}</option>
          </select>
        ) : null}
        <input
          type="search"
          name="q"
          role="combobox"
          placeholder={placeholder}
          aria-label={tStatic(locale, "header.searchLabel")}
          aria-expanded={showPanel}
          aria-controls="header-search-suggestions"
          aria-autocomplete="list"
          aria-haspopup="listbox"
          aria-activedescendant={activeOptionId}
          autoComplete="off"
          autoFocus={isModal}
          value={query.value}
          onInput$={(_, el) => {
            query.value = el.value;
            if (el.value.trim().length < 2) {
              closeHeaderDropdown(headerMenu, "search");
            }
          }}
          onFocus$={() => {
            if (results.value.length > 0 && query.value.trim().length >= 2) {
              headerMenu.openId = "search";
            }
          }}
          onKeyDown$={(event) => {
            if (event.key === "Escape") {
              event.preventDefault();
              closeHeaderDropdown(headerMenu, "search");
              activeIndex.value = -1;
              if (isModal) {
                headerMenu.searchModalOpen = false;
              }
              return;
            }
            if (headerMenu.openId !== "search" || results.value.length === 0) {
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
                void goToHit$(activeIndex.value).then(() => {
                  if (isModal) headerMenu.searchModalOpen = false;
                });
              }
            }
          }}
        />
        <button type="submit" aria-label={tStatic(locale, "header.search")} disabled={searching.value}>
          <SearchIcon size={18} />
        </button>
      </form>

      <div class="header-search-suggestions" hidden={!showPanel}>
        {loading.value ? (
          <p class="header-search-suggestions__status" role="status">
            {tStatic(locale, "common.searching")}
          </p>
        ) : null}

        {!loading.value && results.value.length === 0 ? (
          <p class="header-search-suggestions__status" role="status">
            {tStatic(locale, "common.noResults")}
          </p>
        ) : null}

        <div
          id="header-search-suggestions"
          role="listbox"
          aria-label={tStatic(locale, "header.searchSuggestions")}
        >
          {!loading.value
            ? results.value.map((hit, index) => (
                <div
                  key={`${hit.kind || "item"}-${hit.id}-${hit.platform || ""}`}
                  id={`header-search-option-${hit.kind || "item"}-${hit.id}`}
                  class={`header-search-suggestion${index === activeIndex.value ? " is-active" : ""}`}
                  role="option"
                  aria-selected={index === activeIndex.value}
                  onMouseDown$={(event) => event.preventDefault()}
                  onMouseEnter$={() => {
                    activeIndex.value = index;
                  }}
                  onClick$={async (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    await goToHit$(index);
                    if (isModal) headerMenu.searchModalOpen = false;
                  }}
                >
                  {hit.image_url ? (
                    <img
                      class="header-search-suggestion__thumb"
                      src={hit.image_url}
                      alt=""
                      width={40}
                      height={40}
                      loading="lazy"
                    />
                  ) : (
                    <span class="header-search-suggestion__thumb" aria-hidden="true" />
                  )}
                  <span class="header-search-suggestion__body">
                    <span class="header-search-suggestion__name">{hit.name}</span>
                    <span class="header-search-suggestion__price">
                      {hit.variation_name ? `${hit.variation_name} · ` : ""}
                      {hit.kind === "game" || hit.kind === "gift_card"
                        ? hit.price > 0
                          ? formatPrice(hit.price, settings.currency, locale)
                          : tStatic(locale, "digital.unavailable")
                        : formatPrice(hit.price, settings.currency, locale)}
                    </span>
                  </span>
                </div>
              ))
            : null}
        </div>

        {!loading.value && results.value.length > 0 ? (
          <button
            type="button"
            class="header-search-suggestions__all"
            onMouseDown$={(event) => event.preventDefault()}
            onClick$={async (event) => {
              event.preventDefault();
              event.stopPropagation();
              await submitSearch$(query.value.trim());
              if (isModal) headerMenu.searchModalOpen = false;
            }}
          >
            {tStatic(locale, "common.viewAllResults", { query: query.value.trim() })}
          </button>
        ) : null}
      </div>
    </>
  );

  if (isModal) {
    return (
      <div class="header-search-wrap header-search-wrap--modal">
        <button
          type="button"
          class="header-search-trigger"
          aria-label={tStatic(locale, "header.search")}
          aria-expanded={headerMenu.searchModalOpen}
          onClick$={() => {
            headerMenu.searchModalOpen = true;
          }}
        >
          <SearchIcon size={22} />
        </button>
        {headerMenu.searchModalOpen ? (
          <div class="header-search-modal" role="dialog" aria-modal="true" aria-label={tStatic(locale, "header.search")}>
            <button
              type="button"
              class="header-search-modal__backdrop"
              aria-label={tStatic(locale, "a11y.close")}
              onClick$={closeModal$}
            />
            <div class="header-search-modal__panel">
              <div class="header-search-modal__head">
                <span class="header-search-modal__title">{tStatic(locale, "header.search")}</span>
                <button type="button" class="header-search-modal__close" onClick$={closeModal$}>
                  {tStatic(locale, "a11y.close")}
                </button>
              </div>
              {formAndSuggestions}
            </div>
          </div>
        ) : null}
      </div>
    );
  }

  return <div class="header-search-wrap">{formAndSuggestions}</div>;
});
