import { component$, type QRL, type Signal } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { CloseIcon } from "~/components/icons";
import type { Category } from "~/lib/types";

interface CategoriesDrawerProps {
  categories: Category[];
  open: Signal<boolean>;
  onClose$: QRL<() => void>;
}

export const CategoriesDrawer = component$<CategoriesDrawerProps>(
  ({ categories, open, onClose$ }) => {
    return (
      <div
        class={`categories-drawer${open.value ? " categories-drawer--open" : ""}`}
        aria-hidden={!open.value}
      >
        <button
          type="button"
          class="categories-drawer-backdrop"
          aria-label="Close categories menu"
          onClick$={onClose$}
        />
        <aside
          id="categories-panel"
          class="categories-drawer-panel"
          role="dialog"
          aria-modal="true"
          aria-label="Categories"
        >
          <div class="categories-drawer-head">
            <h2 class="categories-drawer-title">Categories</h2>
            <button
              type="button"
              class="categories-drawer-close"
              aria-label="Close categories menu"
              onClick$={onClose$}
            >
              <CloseIcon size={22} />
            </button>
          </div>
          <nav class="categories-drawer-nav" aria-label="Product categories">
            <ul class="categories-drawer-list">
              <li>
                <Link
                  href="/products"
                  class="categories-drawer-link"
                  onClick$={onClose$}
                >
                  View All Products
                </Link>
              </li>
              {categories.map((cat) => (
                <li key={cat.id}>
                  <Link
                    href={
                      cat.slug
                        ? `/category/${cat.slug}`
                        : `/products?category_id=${cat.id}`
                    }
                    class="categories-drawer-link"
                    onClick$={onClose$}
                  >
                    {cat.name}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
        </aside>
      </div>
    );
  },
);
