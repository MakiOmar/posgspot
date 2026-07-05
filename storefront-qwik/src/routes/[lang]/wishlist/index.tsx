import { component$, useSignal } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { TrashIcon } from "~/components/icons";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { useAuth } from "~/lib/auth-context";
import { formatPrice, productPath } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { usePendingState } from "~/lib/pending-context";
import { removeWishlistItem } from "~/lib/wishlist-actions";
import { useWishlist } from "~/lib/wishlist-context";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const settings = useSiteSettings();
  const wishlist = useWishlist();
  const cart = useCart();
  const auth = useAuth();
  const pending = usePendingState();
  const { locale } = useI18n();
  const removingId = useSignal<number | null>(null);
  const adding = useSignal(false);

  return (
    <section class="container wishlist-page">
      <h1>{tStatic(locale, "wishlist.title")}</h1>

      {!wishlist.hydrated ? (
        <p class="footer-muted">{tStatic(locale, "common.loading")}</p>
      ) : wishlist.items.length === 0 ? (
        <div class="wishlist-empty">
          <p>{tStatic(locale, "wishlist.empty")}</p>
          <Link href={localePath(locale, "/products")} class="btn btn-secondary">
            {tStatic(locale, "cart.continueShopping")}
          </Link>
        </div>
      ) : (
        <ul class="wishlist-grid">
          {wishlist.items.map((product) => {
            const pdpUrl = productPath(product, locale);
            const canAddToCart = Boolean(product.variation_id) && product.in_stock;

            return (
              <li key={product.id} class="wishlist-item">
                <Link href={pdpUrl} class="wishlist-item__media">
                  {product.image_url ? (
                    <img
                      src={product.image_url}
                      alt={product.name}
                      width={160}
                      height={160}
                      loading="lazy"
                    />
                  ) : (
                    <span class="wishlist-item__placeholder" aria-hidden="true" />
                  )}
                </Link>

                <div class="wishlist-item__body">
                  <Link href={pdpUrl} class="wishlist-item__name">
                    {product.name}
                  </Link>
                  {product.variation_name ? (
                    <p class="footer-muted wishlist-item__variant">{product.variation_name}</p>
                  ) : null}
                  <p class="wishlist-item__price">
                    {formatPrice(product.price, settings.value.currency, locale)}
                  </p>
                  <span
                    class={`stock-pill ${product.in_stock ? "stock-pill--in" : "stock-pill--out"}`}
                  >
                    {product.in_stock
                      ? tStatic(locale, "catalog.inStock")
                      : tStatic(locale, "catalog.outOfStock")}
                  </span>

                  <div class="wishlist-item__actions">
                    {canAddToCart ? (
                      <button
                        type="button"
                        class="btn btn-primary btn-block"
                        disabled={adding.value}
                        onClick$={async () => {
                          const variationId = product.variation_id;
                          if (!variationId) {
                            return;
                          }
                          await withPendingFeedback(pending, adding, async () => {
                            await addCartItem(cart, {
                              productId: product.id,
                              variationId,
                              slug: product.slug,
                              name: product.name,
                              variationName: product.variation_name || "DUMMY",
                              price: product.price,
                              quantity: 1,
                              imageUrl: product.image_url,
                            });
                          });
                        }}
                      >
                        {adding.value
                          ? tStatic(locale, "catalog.addingToCart")
                          : tStatic(locale, "catalog.addToCart")}
                      </button>
                    ) : product.has_options ? (
                      <Link href={pdpUrl} class="btn btn-secondary btn-block">
                        {tStatic(locale, "catalog.viewOptions")}
                      </Link>
                    ) : null}

                    <button
                      type="button"
                      class="btn btn-ghost btn-block wishlist-item__remove"
                      disabled={removingId.value === product.id}
                      onClick$={async () => {
                        removingId.value = product.id;
                        try {
                          await removeWishlistItem(wishlist, auth, product.id, locale);
                        } finally {
                          removingId.value = null;
                        }
                      }}
                    >
                      <TrashIcon size={16} />
                      {tStatic(locale, "wishlist.remove")}
                    </button>
                  </div>
                </div>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);

  return {
    title: tStatic(lang, "wishlist.seoTitle", { businessName: settings.business_name }),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
