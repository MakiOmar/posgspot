import { $, component$, useOnDocument } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { CartIcon, CloseIcon, TrashIcon } from "~/components/icons";
import { cartLineKey, cartSubtotal, removeCartItem, totalCartItems } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import {
  closeHeaderDropdown,
  toggleHeaderDropdown,
  useHeaderDropdown,
} from "~/lib/header-dropdown-context";
import { formatPrice } from "~/lib/format";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { StoreSettings } from "~/lib/types";

interface MiniCartProps {
  settings: StoreSettings;
}

export const MiniCart = component$<MiniCartProps>(({ settings }) => {
  const { locale } = useI18n();
  const cart = useCart();
  const headerMenu = useHeaderDropdown();
  const open = headerMenu.openId === "cart";

  const itemCount = totalCartItems(cart);
  const subtotal = cartSubtotal(cart);

  const close$ = $(() => {
    closeHeaderDropdown(headerMenu, "cart");
  });

  const toggle$ = $(() => {
    toggleHeaderDropdown(headerMenu, "cart");
  });

  useOnDocument(
    "click",
    $((event) => {
      const target = event.target as HTMLElement | null;
      if (!target?.closest(".mini-cart")) {
        closeHeaderDropdown(headerMenu, "cart");
      }
    }),
  );

  useOnDocument(
    "keydown",
    $((event) => {
      if ((event as KeyboardEvent).key === "Escape") {
        closeHeaderDropdown(headerMenu, "cart");
      }
    }),
  );

  return (
    <div class={`mini-cart${open ? " mini-cart--open" : ""}`}>
      <button
        type="button"
        class="action-link action-cart mini-cart__trigger"
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-label={tStatic(locale, "header.cart")}
        onClick$={toggle$}
      >
        <span class="action-cart-icon">
          <CartIcon size={22} />
          {itemCount > 0 ? <span class="cart-badge">{itemCount}</span> : null}
        </span>
        <span class="action-text">{formatPrice(subtotal, settings.currency, locale)}</span>
      </button>

      {open ? (
        <div
          class="mini-cart__panel"
          role="dialog"
          aria-label={tStatic(locale, "miniCart.title")}
        >
          <div class="mini-cart__head">
            <h2 class="mini-cart__title">{tStatic(locale, "miniCart.title")}</h2>
            <button
              type="button"
              class="mini-cart__close"
              aria-label={tStatic(locale, "a11y.close")}
              onClick$={close$}
            >
              <CloseIcon size={18} />
            </button>
          </div>

          {cart.items.length === 0 ? (
            <div class="mini-cart__empty">
              <p>{tStatic(locale, "miniCart.empty")}</p>
              <Link href={localePath(locale, "/products")} class="btn btn-secondary btn-block" onClick$={close$}>
                {tStatic(locale, "cart.continueShopping")}
              </Link>
            </div>
          ) : (
            <>
              <ul class="mini-cart__list">
                {cart.items.map((line) => (
                  <li key={cartLineKey(line)} class="mini-cart__line">
                    <div class="mini-cart__thumb-wrap">
                      {line.imageUrl ? (
                        <img
                          src={line.imageUrl}
                          alt=""
                          width={56}
                          height={56}
                          class="mini-cart__thumb"
                          loading="lazy"
                        />
                      ) : (
                        <span class="mini-cart__thumb mini-cart__thumb--placeholder" aria-hidden="true" />
                      )}
                    </div>
                    <div class="mini-cart__line-body">
                      <p class="mini-cart__line-name">{line.name}</p>
                      {line.variationName !== "DUMMY" ? (
                        <p class="mini-cart__line-variant">{line.variationName}</p>
                      ) : null}
                      <p class="mini-cart__line-meta">
                        {tStatic(locale, "miniCart.qtyPrice", {
                          qty: line.quantity,
                          price: formatPrice(line.price, settings.currency, locale),
                        })}
                      </p>
                    </div>
                    <div class="mini-cart__line-end">
                      <strong class="mini-cart__line-total">
                        {formatPrice(line.price * line.quantity, settings.currency, locale)}
                      </strong>
                      <button
                        type="button"
                        class="mini-cart__remove"
                        aria-label={tStatic(locale, "a11y.removeItem")}
                        onClick$={() => removeCartItem(cart, cartLineKey(line))}
                      >
                        <TrashIcon size={14} />
                      </button>
                    </div>
                  </li>
                ))}
              </ul>

              <div class="mini-cart__summary">
                <div class="mini-cart__subtotal-row">
                  <span>{tStatic(locale, "cart.subtotal")}</span>
                  <strong>{formatPrice(subtotal, settings.currency, locale)}</strong>
                </div>
                <Link
                  href={localePath(locale, "/cart")}
                  class="btn btn-secondary btn-block"
                  onClick$={close$}
                >
                  {tStatic(locale, "miniCart.viewCart")}
                </Link>
                <Link
                  href={localePath(locale, "/checkout")}
                  class="btn btn-primary btn-block"
                  onClick$={close$}
                >
                  {tStatic(locale, "cart.checkout")}
                </Link>
              </div>
            </>
          )}
        </div>
      ) : null}
    </div>
  );
});
