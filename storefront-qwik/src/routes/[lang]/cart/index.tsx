import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { TrashIcon } from "~/components/icons";
import { QuantityStepper } from "~/components/ui/quantity-stepper";
import { removeCartItem, setCartQuantity } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { useSiteSettings } from "~/routes/layout";

export default component$(() => {
  const settings = useSiteSettings();
  const cart = useCart();
  const { locale } = useI18n();

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

  const subtotal = cart.items.reduce(
    (sum, line) => sum + line.price * line.quantity,
    0,
  );

  return (
    <section>
      <h1 class="page-title">{tStatic(locale, "cart.title")}</h1>

      <table class="cart-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Price</th>
            <th>Qty</th>
            <th>Total</th>
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
                  label={`Quantity for ${line.name}`}
                  onChange$={(next) => setCartQuantity(cart, line.variationId, next)}
                />
              </td>
              <td>{formatPrice(line.price * line.quantity, settings.value.currency, locale)}</td>
              <td>
                <button
                  type="button"
                  class="btn btn-secondary footer-contact"
                  aria-label="Remove item"
                  onClick$={() => removeCartItem(cart, line.variationId)}
                >
                  <TrashIcon size={16} />
                  Remove
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
  return {
    title: `Cart — ${settings.business_name}`,
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
