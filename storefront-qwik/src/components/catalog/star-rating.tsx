import { component$ } from "@builder.io/qwik";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface StarRatingProps {
  average: number;
  count?: number;
  size?: "sm" | "md";
  showCount?: boolean;
}

/** Read-only star display for aggregates (cards + PDP summary). */
export const StarRating = component$<StarRatingProps>((props) => {
  const { locale } = useI18n();
  const average = Math.max(0, Math.min(5, Number(props.average) || 0));
  const count = props.count ?? 0;
  const size = props.size ?? "sm";
  const showCount = props.showCount ?? count > 0;
  const rounded = Math.round(average * 2) / 2;

  return (
    <span
      class={`star-rating star-rating--${size}`}
      title={tStatic(locale, "reviews.averageTitle", {
        average: average.toFixed(1),
        count: String(count),
      })}
    >
      <span class="star-rating__stars" aria-hidden="true">
        {[1, 2, 3, 4, 5].map((star) => {
          const filled = rounded >= star;
          const half = !filled && rounded >= star - 0.5;
          return (
            <span
              key={star}
              class={`star-rating__star${filled ? " star-rating__star--full" : ""}${half ? " star-rating__star--half" : ""}`}
            >
              ★
            </span>
          );
        })}
      </span>
      {showCount ? (
        <span class="star-rating__meta">
          <span class="star-rating__avg">{average.toFixed(1)}</span>
          <span class="star-rating__count">
            ({tStatic(locale, "reviews.countLabel", { count: String(count) })})
          </span>
        </span>
      ) : null}
      <span class="sr-only">
        {tStatic(locale, "reviews.averageTitle", {
          average: average.toFixed(1),
          count: String(count),
        })}
      </span>
    </span>
  );
});
