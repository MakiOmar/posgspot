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
    return {
      categories: (data.categories ?? []) as DigitalCardCategory[],
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
  const pendingId = useSignal<number | null>(null);

  const addCard$ = $(async (category: DigitalCardCategory) => {
    const sku = list.value.skus.gift_card;
    if (!sku) {
      await toastError(tStatic(lang, "digital.skuMissing"));
      return;
    }
    const price = Number(category.price);
    if (!Number.isFinite(price) || price <= 0) {
      await toastError(tStatic(lang, "digital.unavailable"));
      return;
    }

    pendingId.value = category.id;
    try {
      await checkDigitalCardStock(category.id);
      const digital: CartItemDigital = {
        kind: "card",
        card_category_id: category.id,
        line_key: `card|category:${category.id}`,
        title: category.name,
        price,
      };
      await addCartItem(cart, {
        productId: sku.product_id,
        variationId: sku.variation_id,
        slug: null,
        name: category.name,
        variationName: tStatic(lang, "digital.giftCard"),
        price,
        quantity: 1,
        imageUrl: category.poster_image || sku.image_url,
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
    <section>
      <nav class="content-breadcrumb" aria-label={tStatic(lang, "a11y.breadcrumb")}>
        <Link href={localePath(lang, "/")}>{tStatic(lang, "nav.home")}</Link>
        <span aria-hidden="true"> / </span>
        <span>{tStatic(lang, "nav.giftCards")}</span>
      </nav>

      <h1 class="page-title">{tStatic(lang, "digital.giftCardsTitle")}</h1>
      <p class="footer-muted">{tStatic(lang, "digital.giftCardsLead")}</p>

      {list.value.categories.length === 0 ? (
        <div class="empty-state">{tStatic(lang, "digital.noGiftCards")}</div>
      ) : (
        <div class="product-grid">
          {list.value.categories.map((category) => (
            <article key={category.id} class="product-card">
              <div class="product-card__media">
                {category.poster_image ? (
                  <img
                    src={category.poster_image}
                    alt=""
                    width={320}
                    height={320}
                    loading="lazy"
                  />
                ) : (
                  <span class="product-card__placeholder" aria-hidden="true" />
                )}
              </div>
              <div class="product-card__body">
                <h2 class="product-card__title">{category.name}</h2>
                <p class="product-card__price">
                  {formatPrice(Number(category.price), settings.value.currency, lang)}
                </p>
                <button
                  type="button"
                  class="btn btn-primary"
                  disabled={pendingId.value !== null}
                  onClick$={() => addCard$(category)}
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
