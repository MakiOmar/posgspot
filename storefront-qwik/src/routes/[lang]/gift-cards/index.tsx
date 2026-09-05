import { $, component$, useSignal } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { addCartItem } from "~/lib/cart-actions";
import { useCart } from "~/lib/cart-context";
import { checkDigitalCardStock, fetchDigitalCardCategories } from "~/lib/api";
import { formatPrice } from "~/lib/format";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { CartItemDigital, DigitalCardCategory } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useGiftCards = routeLoader$(async ({ params, redirect }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  try {
    const { data } = await fetchDigitalCardCategories(locale);
    // Strip nested Accounts fields (e.g. cards[]) so Qwik can serialize across $.
    const categories: DigitalCardCategory[] = (data.categories ?? []).map((row) => ({
      id: Number(row.id),
      name: String(row.name ?? ""),
      price: row.price,
      poster_image: row.poster_image ?? null,
    }));
    return {
      categories,
      skus: data.skus,
    };
  } catch (e: unknown) {
    const status = typeof e === "object" && e && "status" in e ? Number((e as { status: number }).status) : 0;
    if (status === 503) {
      throw redirect(302, localePath(locale, "/products"));
    }
    return {
      categories: [] as DigitalCardCategory[],
      skus: { primary: null, secondary: null, gift_card: null },
    };
  }
});

export default component$(() => {
  const list = useGiftCards();
  const settings = useSiteSettings();
  const cart = useCart();
  const nav = useNavigate();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";
  const q = (loc.url.searchParams.get("q") || "").trim().toLowerCase();
  const categories = q
    ? list.value.categories.filter((category) => category.name.toLowerCase().includes(q))
    : list.value.categories;
  const pendingId = useSignal<number | null>(null);

  const addCard$ = $(async (categoryId: number, name: string, priceRaw: number | string, posterImage: string | null) => {
    const sku = list.value.skus.gift_card;
    if (!sku) {
      await toastError(tStatic(lang, "digital.skuMissing"));
      return;
    }
    const price = Number(priceRaw);
    if (!Number.isFinite(price) || price <= 0) {
      await toastError(tStatic(lang, "digital.unavailable"));
      return;
    }

    pendingId.value = categoryId;
    try {
      await checkDigitalCardStock(categoryId);
      const digital: CartItemDigital = {
        kind: "card",
        card_category_id: categoryId,
        line_key: `card|category:${categoryId}`,
        title: name,
        price,
      };
      await addCartItem(cart, {
        productId: sku.product_id,
        variationId: sku.variation_id,
        slug: null,
        name,
        variationName: tStatic(lang, "digital.giftCard"),
        price,
        quantity: 1,
        imageUrl: posterImage || sku.image_url,
        digital,
      });
      await toastSuccess(tStatic(lang, "digital.addedToCart"));
      await nav(localePath(lang, "/cart"));
    } catch (e) {
      await toastError(
        e instanceof Error ? e.message : tStatic(lang, "digital.stockFailed"),
      );
    } finally {
      pendingId.value = null;
    }
  });

  return (
    <section class="digital-catalog">
      <nav class="content-breadcrumb" aria-label={tStatic(lang, "a11y.breadcrumb")}>
        <Link href={localePath(lang, "/")}>{tStatic(lang, "nav.home")}</Link>
        <span aria-hidden="true"> / </span>
        <span>{tStatic(lang, "nav.giftCards")}</span>
      </nav>

      <header class="digital-catalog__header">
        <h1 class="page-title digital-catalog__title">{tStatic(lang, "digital.giftCardsTitle")}</h1>
        <p class="footer-muted digital-catalog__lead">{tStatic(lang, "digital.giftCardsLead")}</p>
      </header>

      {categories.length === 0 ? (
        <div class="empty-state">{tStatic(lang, "digital.noGiftCards")}</div>
      ) : (
        <div class="product-grid digital-catalog__grid">
          {categories.map((category) => (
            <article key={category.id} class="product-card digital-game-card">
              <div class="product-card__media digital-game-card__media">
                {category.poster_image ? (
                  <img
                    class="product-card__image digital-game-card__image"
                    src={category.poster_image}
                    alt={category.name}
                    width={320}
                    height={320}
                    loading="lazy"
                  />
                ) : (
                  <div class="product-card__image digital-game-card__image" aria-hidden="true" />
                )}
              </div>
              <div class="product-card__body digital-game-card__body">
                <h2 class="product-card__name digital-game-card__title">{category.name}</h2>
                <p class="product-card__price digital-game-card__price">
                  {formatPrice(Number(category.price), settings.value.currency, lang)}
                </p>
                <button
                  type="button"
                  class="btn btn-primary digital-game-card__cta"
                  disabled={pendingId.value !== null}
                  onClick$={() =>
                    addCard$(
                      category.id,
                      category.name,
                      category.price,
                      category.poster_image ?? null,
                    )
                  }
                >
                  {pendingId.value === category.id
                    ? tStatic(lang, "digital.adding")
                    : tStatic(lang, "digital.addGiftCard")}
                </button>
              </div>
            </article>
          ))}
        </div>
      )}
    </section>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const lang = resolveValue(useLangParam);
  const settings = resolveValue(useSiteSettings);
  return withStorefrontThemeHead(
    {
      title: tStatic(lang, "digital.giftCardsSeoTitle", { businessName: settings.business_name }),
      meta: [
        {
          name: "description",
          content: tStatic(lang, "digital.giftCardsSeoDescription", {
            businessName: settings.business_name,
          }),
        },
      ],
      links: publicSeoLinks(url.origin, "/gift-cards", lang),
    },
    settings,
  );
};
