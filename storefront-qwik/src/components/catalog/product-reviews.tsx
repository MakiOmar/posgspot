import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { StarRating } from "~/components/catalog/star-rating";
import {
  ApiError,
  fetchProductReviews,
  fetchReviewEligibility,
  submitProductReview,
} from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import type { ProductReviewItem, ReviewEligibility } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";

interface ProductReviewsProps {
  productId: number;
  productSlug: string | null;
  ratingAverage: number;
  ratingCount: number;
}

export const ProductReviews = component$<ProductReviewsProps>((props) => {
  const { locale } = useI18n();
  const auth = useAuth();
  const pending = usePendingState();
  const reviews = useSignal<ProductReviewItem[]>([]);
  const meta = useStore({ current_page: 1, last_page: 1, total: 0 });
  const loading = useSignal(true);
  const submitting = useSignal(false);
  const submittedPending = useSignal(false);
  const eligibility = useSignal<ReviewEligibility | null>(null);
  const form = useStore({ rating: 5, title: "", body: "" });

  const idOrSlug = props.productSlug || String(props.productId);

  const loadReviews$ = $(async (page = 1) => {
    loading.value = true;
    try {
      const result = await fetchProductReviews(idOrSlug, page, 10, locale);
      reviews.value = result.data;
      meta.current_page = result.meta.current_page;
      meta.last_page = result.meta.last_page;
      meta.total = result.meta.total;
    } catch {
      reviews.value = [];
    } finally {
      loading.value = false;
    }
  });

  useVisibleTask$(async ({ track }) => {
    track(() => auth.token);
    track(() => auth.ready);
    await loadReviews$(1);

    if (auth.ready && auth.token) {
      try {
        const { data } = await fetchReviewEligibility(idOrSlug, auth.token, locale);
        eligibility.value = data;
      } catch {
        eligibility.value = null;
      }
    } else {
      eligibility.value = null;
    }
  });

  const submit$ = $(async () => {
    if (!auth.token) {
      return;
    }
    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await submitProductReview(
          idOrSlug,
          auth.token!,
          {
            rating: form.rating,
            title: form.title.trim() || undefined,
            body: form.body.trim(),
          },
          locale,
        );
        submittedPending.value = true;
        eligibility.value = {
          can_review: false,
          already_reviewed: true,
          reason: "pending",
        };
        form.title = "";
        form.body = "";
        form.rating = 5;
        await toastSuccess(data.message || tStatic(locale, "reviews.submitSuccess"));
      } catch (e) {
        const message =
          e instanceof ApiError
            ? e.message || tStatic(locale, "reviews.submitFailed")
            : tStatic(locale, "reviews.submitFailed");
        await toastError(message);
      }
    });
  });

  const average = props.ratingAverage;
  const count = Math.max(props.ratingCount, meta.total);

  return (
    <section class="product-reviews home-section" aria-labelledby="product-reviews-heading">
      <div class="home-section__head">
        <h2 id="product-reviews-heading" class="home-section__title">
          {tStatic(locale, "reviews.title")}
        </h2>
      </div>

      <div class="product-reviews__summary">
        {count > 0 ? (
          <StarRating average={average} count={count} size="md" />
        ) : (
          <p class="footer-muted">{tStatic(locale, "reviews.noneYet")}</p>
        )}
      </div>

      {!auth.ready ? null : auth.token ? (
        eligibility.value?.can_review ? (
          <form class="product-reviews__form" preventdefault:submit onSubmit$={submit$}>
            <h3 class="product-reviews__form-title">{tStatic(locale, "reviews.writeReview")}</h3>
            <label class="form-field">
              <span>{tStatic(locale, "reviews.rating")}</span>
              <select
                class="pdp-select"
                value={String(form.rating)}
                onChange$={(_, el) => {
                  form.rating = Number(el.value);
                }}
              >
                {[5, 4, 3, 2, 1].map((n) => (
                  <option key={n} value={n}>
                    {tStatic(locale, "reviews.starsOption", { count: String(n) })}
                  </option>
                ))}
              </select>
            </label>
            <label class="form-field">
              <span>{tStatic(locale, "reviews.titleField")}</span>
              <input
                type="text"
                maxLength={120}
                value={form.title}
                onInput$={(_, el) => {
                  form.title = el.value;
                }}
                placeholder={tStatic(locale, "reviews.titlePlaceholder")}
              />
            </label>
            <label class="form-field">
              <span>
                {tStatic(locale, "reviews.bodyField")}{" "}
                <span class="form-required" aria-hidden="true">
                  *
                </span>
              </span>
              <textarea
                rows={4}
                required
                minLength={10}
                maxLength={2000}
                value={form.body}
                onInput$={(_, el) => {
                  form.body = el.value;
                }}
                placeholder={tStatic(locale, "reviews.bodyPlaceholder")}
              />
            </label>
            <button type="submit" class="btn btn-primary" disabled={submitting.value}>
              {submitting.value
                ? tStatic(locale, "reviews.submitting")
                : tStatic(locale, "reviews.submit")}
            </button>
          </form>
        ) : submittedPending.value || eligibility.value?.reason === "pending" ? (
          <p class="product-reviews__notice">{tStatic(locale, "reviews.pendingNotice")}</p>
        ) : eligibility.value?.reason === "already_reviewed" ? (
          <p class="product-reviews__notice">{tStatic(locale, "reviews.alreadyReviewed")}</p>
        ) : eligibility.value?.reason === "not_purchased" ? (
          <p class="product-reviews__notice">{tStatic(locale, "reviews.purchaseRequired")}</p>
        ) : null
      ) : (
        <p class="product-reviews__notice">
          {tStatic(locale, "reviews.loginPrompt")}{" "}
          <Link href={localePath(locale, "/login")}>{tStatic(locale, "header.signIn")}</Link>
        </p>
      )}

      {loading.value ? (
        <p class="footer-muted">{tStatic(locale, "common.loading")}</p>
      ) : reviews.value.length > 0 ? (
        <ul class="product-reviews__list">
          {reviews.value.map((review) => (
            <li key={review.id} class="product-reviews__item">
              <div class="product-reviews__item-head">
                <StarRating average={review.rating} count={0} showCount={false} size="sm" />
                <strong class="product-reviews__author">{review.author_name}</strong>
                {review.is_verified_purchase ? (
                  <span class="product-reviews__verified">
                    {tStatic(locale, "reviews.verifiedPurchase")}
                  </span>
                ) : null}
              </div>
              {review.title ? <p class="product-reviews__item-title">{review.title}</p> : null}
              <p class="product-reviews__item-body">{review.body}</p>
            </li>
          ))}
        </ul>
      ) : null}

      {meta.last_page > 1 ? (
        <div class="product-reviews__pager">
          <button
            type="button"
            class="btn"
            disabled={meta.current_page <= 1 || loading.value}
            onClick$={() => loadReviews$(meta.current_page - 1)}
          >
            {tStatic(locale, "common.prev")}
          </button>
          <span class="footer-muted">
            {tStatic(locale, "common.pageOf", {
              current: String(meta.current_page),
              last: String(meta.last_page),
            })}
          </span>
          <button
            type="button"
            class="btn"
            disabled={meta.current_page >= meta.last_page || loading.value}
            onClick$={() => loadReviews$(meta.current_page + 1)}
          >
            {tStatic(locale, "common.next")}
          </button>
        </div>
      ) : null}
    </section>
  );
});
