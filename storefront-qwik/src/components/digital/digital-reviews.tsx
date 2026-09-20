import { $, component$, useSignal } from "@builder.io/qwik";
import { StarRating } from "~/components/catalog/star-rating";
import { ApiError, submitDigitalReview } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import type { DigitalReviewItem } from "~/lib/digital-game";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { toastError, toastSuccess } from "~/lib/notify";

interface DigitalReviewsProps {
  gameId: number;
  average: number;
  count: number;
  items: DigitalReviewItem[];
}

/**
 * Accounts-backed digital reviews: display approved items + public submit (phone).
 * Route-local — not POS purchase-gated reviews.
 */
export const DigitalReviews = component$<DigitalReviewsProps>((props) => {
  const { locale } = useI18n();
  const auth = useAuth();
  const phone = useSignal(auth.contact?.mobile?.trim() || "");
  const stars = useSignal(5);
  const comment = useSignal("");
  const pending = useSignal(false);
  const submitted = useSignal(false);

  const submit$ = $(async () => {
    const trimmedPhone = phone.value.trim();
    if (!trimmedPhone) {
      await toastError(tStatic(locale, "digital.reviewPhone"));
      return;
    }
    pending.value = true;
    try {
      await submitDigitalReview({
        phone: trimmedPhone,
        stars: stars.value,
        comment: comment.value.trim() || undefined,
        game_id: props.gameId,
      }, locale);
      submitted.value = true;
      comment.value = "";
      await toastSuccess(tStatic(locale, "digital.reviewPending"));
    } catch (e) {
      await toastError(
        e instanceof ApiError
          ? e.message || tStatic(locale, "digital.reviewFailed")
          : tStatic(locale, "digital.reviewFailed"),
      );
    } finally {
      pending.value = false;
    }
  });

  return (
    <section class="product-reviews home-section" aria-labelledby="digital-reviews-heading">
      <div class="home-section__head">
        <h2 id="digital-reviews-heading" class="home-section__title">
          {tStatic(locale, "digital.reviewTitle")}
        </h2>
      </div>

      <div class="product-reviews__summary">
        {props.count > 0 ? (
          <StarRating average={props.average} count={props.count} size="md" />
        ) : (
          <p class="footer-muted">{tStatic(locale, "digital.reviewEmpty")}</p>
        )}
      </div>

      {submitted.value ? (
        <p class="product-reviews__notice">{tStatic(locale, "digital.reviewPending")}</p>
      ) : (
        <form class="product-reviews__form" preventdefault:submit onSubmit$={submit$}>
          <h3 class="product-reviews__form-title">{tStatic(locale, "digital.reviewSubmit")}</h3>
          <label class="field">
            <span>{tStatic(locale, "digital.reviewStars")}</span>
            <select
              value={String(stars.value)}
              onChange$={(e) => {
                stars.value = Number((e.target as HTMLSelectElement).value) || 5;
              }}
            >
              {[5, 4, 3, 2, 1].map((n) => (
                <option key={n} value={n}>
                  {tStatic(locale, "reviews.starsOption", { count: String(n) })}
                </option>
              ))}
            </select>
          </label>
          <label class="field">
            <span>{tStatic(locale, "digital.reviewPhone")}</span>
            <input
              type="tel"
              name="phone"
              autocomplete="tel"
              required
              value={phone.value}
              onInput$={(e) => {
                phone.value = (e.target as HTMLInputElement).value;
              }}
            />
          </label>
          <label class="field">
            <span>{tStatic(locale, "digital.reviewComment")}</span>
            <textarea
              name="comment"
              rows={3}
              maxlength={2000}
              value={comment.value}
              onInput$={(e) => {
                comment.value = (e.target as HTMLTextAreaElement).value;
              }}
            />
          </label>
          <button type="submit" class="btn btn-primary" disabled={pending.value}>
            {pending.value
              ? tStatic(locale, "reviews.submitting")
              : tStatic(locale, "digital.reviewSubmit")}
          </button>
        </form>
      )}

      {props.items.length > 0 ? (
        <ul class="product-reviews__list">
          {props.items.map((review) => (
            <li key={review.id || `${review.reviewer_name}-${review.stars}`} class="product-reviews__item">
              <div class="product-reviews__item-head">
                <StarRating average={review.stars} count={0} showCount={false} size="sm" />
                <strong class="product-reviews__author">{review.reviewer_name}</strong>
              </div>
              {review.comment ? (
                <p class="product-reviews__item-body">{review.comment}</p>
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </section>
  );
});
