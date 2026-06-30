import { component$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { TrashIcon } from "~/components/icons";
import { removeCartItem, setCartQuantity } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { formatPrice } from "~/lib/format";
import { useSiteSettings } from "~/routes/layout";

export default component$(() => {
  const settings = useSiteSettings();
  const cart = useCart();

  if (cart.items.length === 0) {
    return (
      <section>
        <h1 class="page-title">Your cart</h1>
        <div class="empty-state">
          <p>Your cart is empty.</p>
          <Link href="/products" class="btn btn-primary">
            Continue shopping
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
      <h1 class="page-title">Your cart</h1>

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
              <td>{formatPrice(line.price, settings.value.currency)}</td>
              <td>
                <input
                  class="qty-input"
                  type="number"
                  min={1}
                  value={line.quantity}
                  onInput$={(event) => {
                    setCartQuantity(
                      cart,
                      line.variationId,
                      Number((event.target as HTMLInputElement).value) || 1,
                    );
                  }}
                />
              </td>
              <td>{formatPrice(line.price * line.quantity, settings.value.currency)}</td>
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
          <span>Subtotal</span>
          <strong>{formatPrice(subtotal, settings.value.currency)}</strong>
        </div>
        <Link href="/checkout" class="btn btn-primary btn-block">
          Proceed to checkout
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
