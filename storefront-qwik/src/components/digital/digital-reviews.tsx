import { $, component$, useSignal } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { StarRating } from "~/components/catalog/star-rating";
import { ApiError, submitDigitalReview } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import type { DigitalReviewItem } from "~/lib/digital-game";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { reviewerAvatarTone, reviewerInitials } from "~/lib/reviewer-avatar";

interface DigitalReviewsProps {
  gameId: number;
  average: number;
  count: number;
  items: DigitalReviewItem[];
}

/**
 * Accounts-backed digital reviews: display approved items + submit for signed-in users.
 * Route-local — not POS purchase-gated reviews.
 */
export const DigitalReviews = component$<DigitalReviewsProps>((props) => {
  const { locale } = useI18n();
  const auth = useAuth();
  const stars = useSignal(5);
  const comment = useSignal("");
  const pending = useSignal(false);
  const submitted = useSignal(false);

  const submit$ = $(async () => {
    if (!auth.token) {
      return;
    }
    const phone = (auth.contact?.mobile || "").trim();
    if (!phone) {
      await toastError(tStatic(locale, "digital.reviewPhoneMissing"));
      return;
    }
    pending.value = true;
    try {
      await submitDigitalReview(
        {
          stars: stars.value,
          comment: comment.value.trim() || undefined,
          game_id: props.gameId,
        },
        auth.token,
        locale,
      );
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
    <section
      id="product-reviews"
      class="product-reviews home-section"
      aria-labelledby="digital-reviews-heading"
    >
      <div class="home-section__head">
        <h2 id="digital-reviews-heading" class="home-section__title">
          {tStatic(locale, "digital.reviewTitle")}
        </h2>
      </div>

      <div class="product-reviews__shell">
        <div class="product-reviews__summary">
          {props.count > 0 ? (
            <StarRating average={props.average} count={props.count} size="md" />
          ) : (
            <p class="footer-muted">{tStatic(locale, "digital.reviewEmpty")}</p>
          )}
        </div>

        {!auth.ready ? null : auth.token ? (
          submitted.value ? (
            <p class="product-reviews__notice">{tStatic(locale, "digital.reviewPending")}</p>
          ) : !(auth.contact?.mobile || "").trim() ? (
            <p class="product-reviews__notice">
              {tStatic(locale, "digital.reviewPhoneMissing")}{" "}
              <Link href={localePath(locale, "/account/profile")}>
                {tStatic(locale, "digital.reviewUpdateProfile")}
              </Link>
            </p>
          ) : (
            <form class="product-reviews__form" preventdefault:submit onSubmit$={submit$}>
              <h3 class="product-reviews__form-title">
                {tStatic(locale, "digital.reviewWrite")}
              </h3>
              <p class="product-reviews__form-hint">
                {tStatic(locale, "digital.reviewAs", {
                  phone: (auth.contact?.mobile || "").trim(),
                })}
              </p>
              <label class="form-field">
                <span>{tStatic(locale, "digital.reviewStars")}</span>
                <select
                  value={String(stars.value)}
                  onChange$={(_, el) => {
                    stars.value = Number(el.value) || 5;
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
                <span>{tStatic(locale, "digital.reviewComment")}</span>
                <textarea
                  name="comment"
                  rows={4}
                  maxLength={2000}
                  value={comment.value}
                  placeholder={tStatic(locale, "digital.reviewCommentPlaceholder")}
                  onInput$={(_, el) => {
                    comment.value = el.value;
                  }}
                />
              </label>
              <div class="product-reviews__form-actions">
                <button type="submit" class="btn btn-primary" disabled={pending.value}>
                  {pending.value
                    ? tStatic(locale, "reviews.submitting")
                    : tStatic(locale, "digital.reviewSubmit")}
                </button>
              </div>
            </form>
          )
        ) : (
          <p class="product-reviews__notice">
            {tStatic(locale, "reviews.loginPrompt")}{" "}
            <Link href={localePath(locale, "/login")}>{tStatic(locale, "header.signIn")}</Link>
          </p>
        )}

        {props.items.length > 0 ? (
          <ul class="product-reviews__list">
            {props.items.map((review) => {
              const name = review.reviewer_name || "Customer";
              const initials = reviewerInitials(name);
              const tone = reviewerAvatarTone(name);
              const avatarUrl = (review.avatar_url || "").trim();
              return (
                <li
                  key={review.id || `${name}-${review.stars}-${review.comment.slice(0, 24)}`}
                  class="product-reviews__item"
                >
                  <div
                    class={`product-reviews__avatar product-reviews__avatar--tone-${tone}`}
                    aria-hidden="true"
                  >
                    {avatarUrl ? (
                      <img src={avatarUrl} alt="" width={44} height={44} loading="lazy" />
                    ) : (
                      <span>{initials}</span>
                    )}
                  </div>
                  <div class="product-reviews__item-main">
                    <div class="product-reviews__item-head">
                      <strong class="product-reviews__author">{name}</strong>
                      <StarRating average={review.stars} count={0} showCount={false} size="sm" />
                    </div>
                    {review.comment ? (
                      <p class="product-reviews__item-body">{review.comment}</p>
                    ) : null}
                  </div>
                </li>
              );
            })}
          </ul>
        ) : null}
      </div>
    </section>
  );
});
