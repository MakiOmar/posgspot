import { component$, type QRL, type Signal } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { CloseIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { Category } from "~/lib/types";

interface CategoriesDrawerProps {
  categories: Category[];
  open: Signal<boolean>;
  onClose$: QRL<() => void>;
}

export const CategoriesDrawer = component$<CategoriesDrawerProps>(
  ({ categories, open, onClose$ }) => {
    const { locale } = useI18n();

    return (
      <div
        class={`categories-drawer${open.value ? " categories-drawer--open" : ""}`}
        aria-hidden={!open.value}
      >
        <button
          type="button"
          class="categories-drawer-backdrop"
          aria-label={tStatic(locale, "common.cancel")}
          onClick$={onClose$}
        />
        <aside
          id="categories-panel"
          class="categories-drawer-panel"
          role="dialog"
          aria-modal="true"
          aria-label={tStatic(locale, "nav.categories")}
        >
          <div class="categories-drawer-head">
            <h2 class="categories-drawer-title">{tStatic(locale, "nav.categories")}</h2>
            <button
              type="button"
              class="categories-drawer-close"
              aria-label={tStatic(locale, "common.cancel")}
              onClick$={onClose$}
            >
              <CloseIcon size={22} />
            </button>
          </div>
          <nav class="categories-drawer-nav" aria-label={tStatic(locale, "nav.categories")}>
            <ul class="categories-drawer-list">
              <li>
                <Link
                  href={localePath(locale, "/products")}
                  class="categories-drawer-link"
                  onClick$={onClose$}
                >
                  {tStatic(locale, "footer.allProducts")}
                </Link>
              </li>
              {categories.map((cat) => (
                <li key={cat.id}>
                  <Link
                    href={
                      cat.slug
                        ? localePath(locale, `/category/${cat.slug}`)
                        : localePath(locale, `/products?category_id=${cat.id}`)
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
