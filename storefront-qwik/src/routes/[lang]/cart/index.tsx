import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { TrashIcon } from "~/components/icons";
import { QuantityStepper } from "~/components/ui/quantity-stepper";
import { ApiError, validateCart } from "~/lib/api";
import { applyCartValidation, cartSubtotal, removeCartItem, setCartQuantity } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const settings = useSiteSettings();
  const cart = useCart();
  const { locale } = useI18n();
  const validating = useSignal(false);
  const stockWarning = useSignal<string | null>(null);
  const pricesUpdated = useSignal(false);
  const validatedSubtotal = useSignal<number | null>(null);

  const cartItemsKey = cart.items
    .map((line) => `${line.variationId}:${line.quantity}`)
    .join("|");

  // Re-validate prices and stock whenever cart lines change (after localStorage hydrate).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => cartItemsKey);
    track(() => cart.hydrated);

    if (!cart.hydrated || cart.items.length === 0) {
      stockWarning.value = null;
      pricesUpdated.value = false;
      validatedSubtotal.value = null;
      return;
    }

    validating.value = true;
    try {
      const { data } = await validateCart({
        items: cart.items.map((line) => ({
          variation_id: line.variationId,
          quantity: line.quantity,
        })),
      });
      pricesUpdated.value = applyCartValidation(cart, data.lines);
      validatedSubtotal.value = data.subtotal;
      stockWarning.value = null;
    } catch (err) {
      pricesUpdated.value = false;
      validatedSubtotal.value = null;
      if (err instanceof ApiError) {
        const messages = Object.values(err.errors).flat();
        stockWarning.value = messages.length ? messages.join(" ") : err.message;
      } else {
        stockWarning.value =
          err instanceof Error ? err.message : tStatic(locale, "cart.stockIssue");
      }
    } finally {
      validating.value = false;
    }
  });

  if (cart.items.length === 0) {
    return (
      <section>
        <h1 class="page-title">{tStatic(locale, "cart.title")}</h1>
        <div class="empty-state">
          <p>{tStatic(locale, "cart.empty")}</p>
          <Link href={localePath(locale, "/products")} class="btn btn-primary">
            {tStatic(locale, "cart.continueShopping")}
          </Link>
        </div>
      </section>
    );
  }

  const subtotal =
    validatedSubtotal.value !== null ? validatedSubtotal.value : cartSubtotal(cart);

  return (
    <section>
      <h1 class="page-title">{tStatic(locale, "cart.title")}</h1>

      {validating.value ? (
        <p class="footer-muted cart-status" role="status">
          {tStatic(locale, "cart.refreshing")}
        </p>
      ) : null}

      {stockWarning.value ? (
        <p class="alert alert-error" role="alert">
          {stockWarning.value}
        </p>
      ) : null}

      {!validating.value && pricesUpdated.value && !stockWarning.value ? (
        <p class="alert alert-success" role="status">
          {tStatic(locale, "cart.pricesUpdated")}
        </p>
      ) : null}

      <table class="cart-table">
        <thead>
          <tr>
            <th>{tStatic(locale, "cart.product")}</th>
            <th>{tStatic(locale, "cart.price")}</th>
            <th>{tStatic(locale, "cart.qty")}</th>
            <th>{tStatic(locale, "cart.total")}</th>
            <th />
          </tr>
        </thead>
        <tbody>
          {cart.items.map((line) => (
            <tr key={line.variationId}>
              <td>
                <strong>{line.name}</strong>
                {line.variationName !== "DUMMY" ? (
                  <div class="footer-muted">{line.variationName}</div>
                ) : null}
              </td>
              <td>{formatPrice(line.price, settings.value.currency, locale)}</td>
              <td>
                <QuantityStepper
                  value={line.quantity}
                  label={tStatic(locale, "a11y.quantityFor", { name: line.name })}
                  onChange$={(next) => setCartQuantity(cart, line.variationId, next)}
                />
              </td>
              <td>{formatPrice(line.price * line.quantity, settings.value.currency, locale)}</td>
              <td>
                <button
                  type="button"
                  class="btn btn-secondary footer-contact"
                  aria-label={tStatic(locale, "a11y.removeItem")}
                  onClick$={() => removeCartItem(cart, line.variationId)}
                >
                  <TrashIcon size={16} />
                  {tStatic(locale, "cart.remove")}
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <div class="cart-summary">
        <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "1rem" }}>
          <span>{tStatic(locale, "cart.subtotal")}</span>
          <strong>{formatPrice(subtotal, settings.value.currency, locale)}</strong>
        </div>
        <Link href={localePath(locale, "/checkout")} class="btn btn-primary btn-block">
          {tStatic(locale, "cart.checkout")}
        </Link>
      </div>
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "cart.seoTitle", { businessName: settings.business_name }),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
