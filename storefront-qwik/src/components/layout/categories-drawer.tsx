import { component$, type QRL } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { CloseIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { Category } from "~/lib/types";

interface CategoriesDrawerProps {
  categories: Category[];
  open: boolean;
  onClose$: QRL<() => void>;
}

export const CategoriesDrawer = component$<CategoriesDrawerProps>(
  ({ categories, open, onClose$ }) => {
    const { locale } = useI18n();

    return (
      <div
        class={`side-drawer categories-drawer${open ? " side-drawer--open" : ""}`}
        aria-hidden={!open}
      >
        <button
          type="button"
          class="side-drawer-backdrop"
          aria-label={tStatic(locale, "common.cancel")}
          onClick$={onClose$}
        />
        <aside
          id="categories-panel"
          class="side-drawer-panel"
          role="dialog"
          aria-modal="true"
          aria-label={tStatic(locale, "nav.categories")}
        >
          <div class="side-drawer-head">
            <h2 class="side-drawer-title">{tStatic(locale, "nav.categories")}</h2>
            <button
              type="button"
              class="side-drawer-close"
              aria-label={tStatic(locale, "common.cancel")}
              onClick$={onClose$}
            >
              <CloseIcon size={22} />
            </button>
          </div>
          <nav class="side-drawer-nav" aria-label={tStatic(locale, "nav.categories")}>
            <ul class="side-drawer-list">
              <li>
                <Link
                  href={localePath(locale, "/products")}
                  class="side-drawer-link"
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
                    class="side-drawer-link"
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
