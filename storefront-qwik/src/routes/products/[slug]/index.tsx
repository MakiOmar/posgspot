import { $, component$, useSignal } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { AvailabilityModal } from "~/components/catalog/availability-modal";
import { JsonLd } from "~/components/seo/json-ld";
import { QuantityStepper } from "~/components/ui/quantity-stepper";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { fetchAvailability, fetchProduct } from "~/lib/api";
import { formatPrice } from "~/lib/format";
import type { ProductAvailability, ProductVariation } from "~/lib/types";
import { useSiteSettings } from "~/routes/layout";

export const useProductDetail = routeLoader$(async ({ params, status }) => {
  try {
    const { data } = await fetchProduct(params.slug);
    return data;
  } catch {
    throw status(404);
  }
});

export default component$(() => {
  const settings = useSiteSettings();
  const product = useProductDetail();
  const cart = useCart();

  const selectedVariation = useSignal<ProductVariation>(
    product.value.variations[0] || {
      id: 0,
      name: "",
      sub_sku: "",
      price: 0,
      in_stock: false,
      qty_available: 0,
      images: [],
    },
  );
  const quantity = useSignal(1);

  const modalOpen = useSignal(false);
  const modalLoading = useSignal(false);
  const modalError = useSignal<string | null>(null);
  const availability = useSignal<ProductAvailability | null>(null);

  const openAvailability$ = $(async () => {
    modalOpen.value = true;
    modalLoading.value = true;
    modalError.value = null;
    availability.value = null;

    try {
      const { data } = await fetchAvailability(
        product.value.id,
        selectedVariation.value.id,
      );
      availability.value = data;
    } catch {
      modalError.value = "Could not load store availability.";
    } finally {
      modalLoading.value = false;
    }
  });

  const closeModal$ = $(() => {
    modalOpen.value = false;
  });

  const p = product.value;
  const heroImage = p.images[0] || null;
  const currency = settings.value.currency;
  const variation = selectedVariation.value;
  const stockMax =
    p.enable_stock && variation.in_stock && variation.qty_available > 0
      ? Math.floor(variation.qty_available)
      : undefined;

  return (
    <>
      <JsonLd
        data={{
          "@context": "https://schema.org",
          "@type": "Product",
          name: p.name,
          sku: p.sku,
          image: p.images,
          description: p.description || undefined,
          offers: {
            "@type": "Offer",
            price: selectedVariation.value.price,
            priceCurrency: currency.code,
            availability: selectedVariation.value.in_stock
              ? "https://schema.org/InStock"
              : "https://schema.org/OutOfStock",
          },
        }}
      />

      <article class="pdp">
        <div class="pdp-gallery">
          {heroImage ? (
            <img
              src={heroImage}
              alt={p.name}
              width={600}
              height={600}
              loading="eager"
              fetchPriority="high"
            />
          ) : (
            <div style={{ aspectRatio: "1", background: "var(--gs-surface-2)" }} />
          )}
        </div>

        <div class="pdp-info">
          <h1>{p.name}</h1>
          {p.brand ? <p class="footer-muted">Brand: {p.brand.name}</p> : null}
          {p.category ? <p class="footer-muted">Category: {p.category.name}</p> : null}

          <div class="pdp-price">
            {selectedVariation.value.on_sale && selectedVariation.value.compare_at_price != null ? (
              <>
                <span class="product-card__price product-card__price--sale">
                  {formatPrice(selectedVariation.value.price, currency)}
                </span>
                <span class="product-card__price-compare" style={{ marginLeft: "0.5rem" }}>
                  {formatPrice(selectedVariation.value.compare_at_price, currency)}
                </span>
              </>
            ) : (
              formatPrice(selectedVariation.value.price, currency)
            )}
          </div>

          {p.variations.length > 1 ? (
            <label>
              <span class="footer-muted">Variation</span>
              <select
                class="pdp-select"
                onChange$={(event) => {
                  const id = Number((event.target as HTMLSelectElement).value);
                  const found = p.variations.find((v) => v.id === id);
                  if (found) {
                    selectedVariation.value = found;
                    quantity.value = 1;
                  }
                }}
              >
                {p.variations.map((v) => (
                  <option key={v.id} value={v.id} selected={v.id === selectedVariation.value.id}>
                    {`${v.name} — ${formatPrice(v.price, currency)}`}
                  </option>
                ))}
              </select>
            </label>
          ) : null}

          <span
            class={`stock-pill ${selectedVariation.value.in_stock ? "stock-pill--in" : "stock-pill--out"}`}
          >
            {selectedVariation.value.in_stock ? "In stock" : "Out of stock"}
          </span>

          {p.description ? (
            <div
              style={{ marginTop: "1.25rem", color: "var(--gs-muted)" }}
              dangerouslySetInnerHTML={p.description}
            />
          ) : null}

          <div class="pdp-actions">
            <div class="pdp-qty-field">
              <span class="footer-muted">Qty</span>
              <QuantityStepper
                value={quantity.value}
                max={stockMax}
                disabled={!variation.in_stock}
                label={`Quantity for ${p.name}`}
                onChange$={(next) => {
                  quantity.value = next;
                }}
              />
            </div>
            <button
              type="button"
              class="btn btn-primary"
              onClick$={async () => {
                const variation = selectedVariation.value;
                if (!variation.id) {
                  return;
                }
                await addCartItem(cart, {
                  productId: product.value.id,
                  variationId: variation.id,
                  slug: product.value.slug,
                  name: product.value.name,
                  variationName: variation.name,
                  price: variation.price,
                  quantity: quantity.value,
                  imageUrl: product.value.images[0] || null,
                });
              }}
              disabled={!variation.in_stock}
            >
              Add to cart
            </button>
            <button type="button" class="btn btn-secondary" onClick$={openAvailability$}>
              Check store availability
            </button>
          </div>
        </div>
      </article>

      {modalOpen.value ? (
        <div class="modal-backdrop" role="presentation" onClick$={closeModal$}>
          <div onClick$={(event) => event.stopPropagation()}>
            <AvailabilityModal
              open={modalOpen.value}
              loading={modalLoading.value}
              error={modalError.value}
              availability={availability.value}
              onClose$={closeModal$}
            />
          </div>
        </div>
      ) : null}
    </>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const product = resolveValue(useProductDetail);
  const title = `${product.name} — ${settings.business_name}`;
  const description =
    product.description?.replace(/<[^>]+>/g, "").slice(0, 160) ||
    `Buy ${product.name} at ${settings.business_name}.`;
  const image = product.images[0] || undefined;
  const canonical = `${url.origin}/products/${product.slug || product.id}`;

  return {
    title,
    meta: [
      { name: "description", content: description },
      { property: "og:title", content: title },
      { property: "og:description", content: description },
      { property: "og:type", content: "product" },
      { property: "og:url", content: canonical },
      ...(image ? [{ property: "og:image", content: image }] : []),
      { name: "twitter:card", content: "summary_large_image" },
    ],
    links: [{ rel: "canonical", href: canonical }],
  };
};
