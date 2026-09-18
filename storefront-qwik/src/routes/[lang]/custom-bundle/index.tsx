import { $, component$, useSignal, useTask$, useComputed$ } from "@builder.io/qwik";
import {
  Link,
  routeLoader$,
  useNavigate,
  type DocumentHead,
} from "@builder.io/qwik-city";
import { CartPlusIcon, MinusIcon } from "~/components/icons";
import { JsonLd } from "~/components/seo/json-ld";
import {
  ApiError,
  fetchCustomBundleMeta,
  fetchCustomBundleProducts,
  fetchProduct,
} from "~/lib/api";
import { addCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CartItem, CustomBundleMeta, ProductSummary } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export interface BundleSelectionLine {
  key: string;
  productId: number;
  variationId: number;
  slug: string | null;
  name: string;
  variationName: string;
  price: number;
  quantity: number;
  imageUrl: string | null;
}

export const useCustomBundlePage = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.custom_bundle?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  try {
    const { data } = await fetchCustomBundleMeta(locale);
    return {
      meta: data,
      unavailable: false as const,
    };
  } catch (e) {
    const status = e instanceof ApiError ? e.status : 0;
    if (status === 404) {
      throw redirect(302, localePath(locale, "/"));
    }
    return {
      meta: {
        enabled: true,
        min_items: settings.custom_bundle?.min_items ?? 2,
        max_items: settings.custom_bundle?.max_items ?? 15,
        platforms: [
          { id: "ps5", label: "PS5" },
          { id: "ps4", label: "PS4" },
        ],
        tabs: [{ id: "all", label: tStatic(locale, "customBundle.tabAll") }],
      } satisfies CustomBundleMeta,
      unavailable: true as const,
    };
  }
});

/** Physical Custom Bundle builder → cart → checkout. */
export default component$(() => {
  const page = useCustomBundlePage();
  const settings = useSiteSettings();
  const currency = settings.value.currency;
  const { locale } = useI18n();
  const cart = useCart();
  const nav = useNavigate();

  const platform = useSignal<string>("");
  const tab = useSignal<string>("all");
  const query = useSignal("");
  const categoryTabs = useSignal<Array<{ id: string; label: string }>>([
    { id: "all", label: tStatic(locale, "customBundle.tabAll") },
  ]);
  const products = useSignal<ProductSummary[]>([]);
  const loading = useSignal(false);
  const loadError = useSignal("");
  const selection = useSignal<BundleSelectionLine[]>([]);
  const pendingKey = useSignal<string | null>(null);
  const variationPicker = useSignal<{
    productId: number;
    name: string;
    slug: string | null;
    imageUrl: string | null;
    options: Array<{ id: number; name: string; price: number; in_stock: boolean }>;
  } | null>(null);
  const submitting = useSignal(false);

  const meta = page.value.meta;
  const minItems = meta.min_items || 2;
  const maxItems = meta.max_items || 15;

  const selectionCount = useComputed$(() =>
    selection.value.reduce((sum, line) => sum + line.quantity, 0),
  );
  const selectionTotal = useComputed$(() =>
    selection.value.reduce((sum, line) => sum + line.price * line.quantity, 0),
  );
  const canSubmit = useComputed$(() => {
    const n = selectionCount.value;
    return n >= minItems && n <= maxItems && !submitting.value;
  });

  const loadProducts$ = $(async () => {
    if (!platform.value) {
      products.value = [];
      return;
    }
    loading.value = true;
    loadError.value = "";
    try {
      const result = await fetchCustomBundleProducts(
        {
          platform: platform.value,
          tab: tab.value,
          q: query.value.trim() || undefined,
          per_page: 40,
        },
        locale,
      );
      products.value = result.data ?? [];
    } catch (e) {
      products.value = [];
      loadError.value =
        e instanceof ApiError
          ? e.message || tStatic(locale, "customBundle.loadFailed")
          : tStatic(locale, "customBundle.loadFailed");
    } finally {
      loading.value = false;
    }
  });

  useTask$(async ({ track }) => {
    track(() => platform.value);
    if (!platform.value) {
      categoryTabs.value = [{ id: "all", label: tStatic(locale, "customBundle.tabAll") }];
      tab.value = "all";
      products.value = [];
      return;
    }
    try {
      const { data } = await fetchCustomBundleMeta(locale, platform.value);
      const tabs = data.tabs?.length
        ? data.tabs
        : [{ id: "all", label: tStatic(locale, "customBundle.tabAll") }];
      categoryTabs.value = tabs;
      if (!tabs.some((t) => t.id === tab.value)) {
        tab.value = "all";
      }
    } catch {
      categoryTabs.value = [{ id: "all", label: tStatic(locale, "customBundle.tabAll") }];
      tab.value = "all";
    }
  });

  useTask$(async ({ track }) => {
    track(() => platform.value);
    track(() => tab.value);
    if (!platform.value) {
      products.value = [];
      return;
    }
    await loadProducts$();
  });

  const addLine$ = $(async (line: BundleSelectionLine) => {
    const count = selection.value.reduce((sum, row) => sum + row.quantity, 0);
    if (count >= maxItems) {
      await toastError(tStatic(locale, "customBundle.maxReached", { max: String(maxItems) }));
      return;
    }
    const existing = selection.value.find((row) => row.key === line.key);
    if (existing) {
      if (count + 1 > maxItems) {
        await toastError(tStatic(locale, "customBundle.maxReached", { max: String(maxItems) }));
        return;
      }
      selection.value = selection.value.map((row) =>
        row.key === line.key ? { ...row, quantity: row.quantity + 1 } : row,
      );
    } else {
      selection.value = [...selection.value, line];
    }
  });

  const tryAddProduct$ = $(async (product: ProductSummary) => {
    if (!product.variation_id || !product.in_stock) {
      return;
    }
    const key = `v:${product.variation_id}`;
    pendingKey.value = key;
    try {
      if (product.has_options) {
        const { data: detail } = await fetchProduct(String(product.id), locale);
        const options = (detail.variations ?? [])
          .filter((v) => v.in_stock)
          .map((v) => ({
            id: v.id,
            name: v.name,
            price: v.price,
            in_stock: v.in_stock,
          }));
        if (options.length === 0) {
          await toastError(tStatic(locale, "customBundle.unavailable"));
          return;
        }
        if (options.length === 1) {
          const only = options[0];
          await addLine$({
            key: `v:${only.id}`,
            productId: product.id,
            variationId: only.id,
            slug: product.slug,
            name: product.name,
            variationName: only.name,
            price: only.price,
            quantity: 1,
            imageUrl: product.image_url,
          });
          return;
        }
        variationPicker.value = {
          productId: product.id,
          name: product.name,
          slug: product.slug,
          imageUrl: product.image_url,
          options,
        };
        return;
      }

      await addLine$({
        key,
        productId: product.id,
        variationId: product.variation_id,
        slug: product.slug,
        name: product.name,
        variationName: product.variation_name || "",
        price: product.price,
        quantity: 1,
        imageUrl: product.image_url,
      });
    } catch (e) {
      await toastError(
        e instanceof Error ? e.message : tStatic(locale, "customBundle.loadFailed"),
      );
    } finally {
      pendingKey.value = null;
    }
  });

  const pickVariation$ = $(async (variationId: number) => {
    const picker = variationPicker.value;
    if (!picker) return;
    const option = picker.options.find((o) => o.id === variationId);
    if (!option) return;
    await addLine$({
      key: `v:${option.id}`,
      productId: picker.productId,
      variationId: option.id,
      slug: picker.slug,
      name: picker.name,
      variationName: option.name,
      price: option.price,
      quantity: 1,
      imageUrl: picker.imageUrl,
    });
    variationPicker.value = null;
  });

  const removeLine$ = $((key: string) => {
    selection.value = selection.value.filter((row) => row.key !== key);
  });

  /** Remove one unit of a catalog product from the bundle (any matching variation). */
  const removeOneOfProduct$ = $((productId: number, variationId: number | null) => {
    const preferredKey = variationId ? `v:${variationId}` : null;
    const match =
      (preferredKey
        ? selection.value.find((row) => row.key === preferredKey)
        : undefined) ||
      selection.value.find((row) => row.productId === productId);
    if (!match) return;
    if (match.quantity <= 1) {
      selection.value = selection.value.filter((row) => row.key !== match.key);
      return;
    }
    selection.value = selection.value.map((row) =>
      row.key === match.key ? { ...row, quantity: row.quantity - 1 } : row,
    );
  });

  const changeQty$ = $((key: string, delta: number) => {
    const next: BundleSelectionLine[] = [];
    let total = 0;
    for (const row of selection.value) {
      if (row.key !== key) {
        total += row.quantity;
        next.push(row);
        continue;
      }
      const qty = row.quantity + delta;
      if (qty <= 0) {
        continue;
      }
      if (delta > 0 && total + qty > maxItems) {
        continue;
      }
      total += qty;
      next.push({ ...row, quantity: qty });
    }
    selection.value = next;
  });

  const submit$ = $(async () => {
    const n = selection.value.reduce((sum, row) => sum + row.quantity, 0);
    if (n < minItems) {
      await toastError(tStatic(locale, "customBundle.minRequired", { min: String(minItems) }));
      return;
    }
    if (n > maxItems) {
      await toastError(tStatic(locale, "customBundle.maxReached", { max: String(maxItems) }));
      return;
    }

    submitting.value = true;
    try {
      const items: CartItem[] = selection.value.map((row) => ({
        productId: row.productId,
        variationId: row.variationId,
        slug: row.slug,
        name: row.name,
        variationName: row.variationName,
        price: row.price,
        quantity: row.quantity,
        imageUrl: row.imageUrl,
      }));
      await addCartItems(cart, items);
      await toastSuccess(tStatic(locale, "customBundle.addedToCart"));
      await nav(localePath(locale, "/checkout"));
    } catch (e) {
      await toastError(
        e instanceof Error ? e.message : tStatic(locale, "customBundle.submitFailed"),
      );
    } finally {
      submitting.value = false;
    }
  });

  const search$ = $(async () => {
    await loadProducts$();
  });

  const howToLd = {
    "@context": "https://schema.org",
    "@type": "HowTo",
    name: tStatic(locale, "customBundle.title"),
    description: tStatic(locale, "customBundle.lead"),
    step: [
      {
        "@type": "HowToStep",
        position: 1,
        name: tStatic(locale, "customBundle.step1Title"),
        text: tStatic(locale, "customBundle.step1Text"),
      },
      {
        "@type": "HowToStep",
        position: 2,
        name: tStatic(locale, "customBundle.step2Title"),
        text: tStatic(locale, "customBundle.step2Text", {
          min: String(minItems),
          max: String(maxItems),
        }),
      },
      {
        "@type": "HowToStep",
        position: 3,
        name: tStatic(locale, "customBundle.step3Title"),
        text: tStatic(locale, "customBundle.step3Text"),
      },
    ],
  };

  return (
    <article class="content-page custom-bundle-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "nav.customBundle")}</span>
      </nav>

      <p class="custom-bundle-badge">{tStatic(locale, "customBundle.badge")}</p>
      <h1 class="content-title">{tStatic(locale, "customBundle.title")}</h1>
      <p class="content-prose custom-bundle-lead">{tStatic(locale, "customBundle.lead")}</p>

      <ol class="custom-bundle-steps">
        <li>
          <strong>{tStatic(locale, "customBundle.step1Title")}</strong>
          <span>{tStatic(locale, "customBundle.step1Text")}</span>
        </li>
        <li>
          <strong>{tStatic(locale, "customBundle.step2Title")}</strong>
          <span>
            {tStatic(locale, "customBundle.step2Text", {
              min: String(minItems),
              max: String(maxItems),
            })}
          </span>
        </li>
        <li>
          <strong>{tStatic(locale, "customBundle.step3Title")}</strong>
          <span>{tStatic(locale, "customBundle.step3Text")}</span>
        </li>
      </ol>

      {page.value.unavailable ? (
        <p class="alert alert-warning" role="status">
          {tStatic(locale, "customBundle.loadFailed")}
        </p>
      ) : null}

      <div class="custom-bundle-layout">
        <div class="custom-bundle-builder">
          <section class="custom-bundle-section">
            <h2>{tStatic(locale, "customBundle.choosePlatform")}</h2>
            <div class="custom-bundle-platforms" role="group" aria-label={tStatic(locale, "customBundle.choosePlatform")}>
              {meta.platforms.map((p) => (
                <button
                  key={p.id}
                  type="button"
                  class={`custom-bundle-platform${platform.value === p.id ? " is-active" : ""}`}
                  aria-pressed={platform.value === p.id}
                  onClick$={() => {
                    platform.value = p.id;
                    tab.value = "all";
                  }}
                >
                  {p.label}
                </button>
              ))}
            </div>
          </section>

          <section class="custom-bundle-section">
            <h2>{tStatic(locale, "customBundle.pickItems")}</h2>
            {!platform.value ? (
              <p class="footer-muted">{tStatic(locale, "customBundle.choosePlatformFirst")}</p>
            ) : (
              <>
                <div class="custom-bundle-tabs" role="tablist">
                  {categoryTabs.value.map((t) => (
                    <button
                      key={t.id}
                      type="button"
                      role="tab"
                      class={`custom-bundle-tab${tab.value === t.id ? " is-active" : ""}`}
                      aria-selected={tab.value === t.id}
                      onClick$={() => {
                        tab.value = t.id;
                      }}
                    >
                      {t.label}
                    </button>
                  ))}
                </div>
                <form
                  class="custom-bundle-search"
                  preventdefault:submit
                  onSubmit$={search$}
                >
                  <label class="sr-only" for="custom-bundle-q">
                    {tStatic(locale, "customBundle.search")}
                  </label>
                  <input
                    id="custom-bundle-q"
                    type="search"
                    value={query.value}
                    placeholder={tStatic(locale, "customBundle.searchPlaceholder")}
                    onInput$={(e) => {
                      query.value = (e.target as HTMLInputElement).value;
                    }}
                  />
                  <button type="submit" class="btn btn-secondary">
                    {tStatic(locale, "customBundle.search")}
                  </button>
                </form>

                {loading.value ? (
                  <p class="footer-muted" role="status">
                    {tStatic(locale, "common.loading")}
                  </p>
                ) : null}
                {loadError.value ? (
                  <p class="alert alert-error" role="alert">
                    {loadError.value}
                  </p>
                ) : null}
                {!loading.value && !loadError.value && products.value.length === 0 ? (
                  <p class="footer-muted">{tStatic(locale, "customBundle.empty")}</p>
                ) : null}

                <ul class="custom-bundle-product-list">
                  {products.value.map((product) => {
                    const key = `v:${product.variation_id ?? product.id}`;
                    const busy = pendingKey.value === key;
                    const inBundle = selection.value.some(
                      (row) =>
                        row.productId === product.id ||
                        (product.variation_id != null &&
                          row.key === `v:${product.variation_id}`),
                    );
                    return (
                      <li key={product.id} class="custom-bundle-product">
                        <div class="custom-bundle-product__media">
                          {product.image_url ? (
                            <img src={product.image_url} alt="" width={64} height={64} loading="lazy" />
                          ) : (
                            <span class="custom-bundle-product__placeholder" aria-hidden="true" />
                          )}
                        </div>
                        <div class="custom-bundle-product__body">
                          <div class="custom-bundle-product__name">{product.name}</div>
                          {product.variation_name ? (
                            <div class="footer-muted">{product.variation_name}</div>
                          ) : null}
                          <div class="custom-bundle-product__price">
                            {formatPrice(product.price, currency, locale)}
                          </div>
                        </div>
                        <button
                          type="button"
                          class={`custom-bundle-cart-btn${inBundle ? " is-remove" : ""}`}
                          disabled={
                            busy ||
                            (!inBundle &&
                              (!product.in_stock || !product.variation_id))
                          }
                          aria-label={
                            inBundle
                              ? tStatic(locale, "customBundle.removeOne")
                              : tStatic(locale, "customBundle.add")
                          }
                          onClick$={() => {
                            if (inBundle) {
                              removeOneOfProduct$(
                                product.id,
                                product.variation_id ?? null,
                              );
                              return;
                            }
                            tryAddProduct$(product);
                          }}
                        >
                          {busy ? (
                            <span class="sr-only">{tStatic(locale, "common.loading")}</span>
                          ) : inBundle ? (
                            <MinusIcon size={22} />
                          ) : (
                            <CartPlusIcon size={22} />
                          )}
                        </button>
                      </li>
                    );
                  })}
                </ul>
              </>
            )}
          </section>
        </div>

        <aside class="custom-bundle-tray" aria-label={tStatic(locale, "customBundle.yourBundle")}>
          <h2>{tStatic(locale, "customBundle.yourBundle")}</h2>
          {selection.value.length === 0 ? (
            <p class="footer-muted">{tStatic(locale, "customBundle.trayEmpty")}</p>
          ) : (
            <ul class="custom-bundle-tray-list">
              {selection.value.map((line) => (
                <li key={line.key} class="custom-bundle-tray-item">
                  <div>
                    <div class="custom-bundle-tray-item__name">{line.name}</div>
                    {line.variationName ? (
                      <div class="footer-muted">{line.variationName}</div>
                    ) : null}
                    <div class="custom-bundle-tray-item__price">
                      {formatPrice(line.price * line.quantity, currency, locale)}
                    </div>
                  </div>
                  <div class="custom-bundle-tray-item__actions">
                    <button type="button" class="btn btn-secondary" onClick$={() => changeQty$(line.key, -1)}>
                      −
                    </button>
                    <span>{line.quantity}</span>
                    <button type="button" class="btn btn-secondary" onClick$={() => changeQty$(line.key, 1)}>
                      +
                    </button>
                    <button
                      type="button"
                      class="btn btn-danger"
                      onClick$={() => removeLine$(line.key)}
                    >
                      {tStatic(locale, "common.remove")}
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}

          <div class="custom-bundle-tray-summary">
            <div>
              {tStatic(locale, "customBundle.itemCount", {
                count: String(selectionCount.value),
                max: String(maxItems),
              })}
            </div>
            <div class="custom-bundle-tray-total">
              {formatPrice(selectionTotal.value, currency, locale)}
            </div>
          </div>

          <button
            type="button"
            class="btn btn-primary custom-bundle-submit"
            disabled={!canSubmit.value}
            onClick$={submit$}
          >
            {submitting.value
              ? tStatic(locale, "common.loading")
              : tStatic(locale, "customBundle.checkout")}
          </button>
          {selectionCount.value > 0 && selectionCount.value < minItems ? (
            <p class="footer-muted">
              {tStatic(locale, "customBundle.minRequired", { min: String(minItems) })}
            </p>
          ) : null}
        </aside>
      </div>

      {variationPicker.value ? (
        <div class="custom-bundle-modal" role="dialog" aria-modal="true">
          <div class="custom-bundle-modal__card">
            <h3>{tStatic(locale, "customBundle.chooseVariation")}</h3>
            <p class="footer-muted">{variationPicker.value.name}</p>
            <ul class="custom-bundle-variation-list">
              {variationPicker.value.options.map((opt) => (
                <li key={opt.id}>
                  <button
                    type="button"
                    class="btn btn-secondary"
                    onClick$={() => pickVariation$(opt.id)}
                  >
                    {opt.name} — {formatPrice(opt.price, currency, locale)}
                  </button>
                </li>
              ))}
            </ul>
            <button
              type="button"
              class="btn btn-secondary"
              onClick$={() => {
                variationPicker.value = null;
              }}
            >
              {tStatic(locale, "common.cancel")}
            </button>
          </div>
        </div>
      ) : null}

      <JsonLd data={howToLd} />
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const title = `${tStatic(lang, "customBundle.title")} — ${settings.business_name}`;
  const description = tStatic(lang, "customBundle.lead");

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { property: "og:url", content: url.href },
        { name: "twitter:card", content: "summary" },
        { name: "twitter:title", content: title },
        { name: "twitter:description", content: description },
      ],
      links: publicSeoLinks(url.origin, "/custom-bundle", lang),
    },
    settings,
  );
};
