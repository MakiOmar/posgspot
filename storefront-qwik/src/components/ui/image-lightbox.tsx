import {
  $,
  component$,
  type Signal,
  useVisibleTask$,
} from "@builder.io/qwik";
import { tStatic, useI18n } from "~/lib/i18n/context";

export type LightboxImage = {
  src: string;
  alt?: string;
};

type Props = {
  images: LightboxImage[];
  /** `null` = closed; otherwise open at this index. */
  index: Signal<number | null>;
};

/**
 * Accessible fullscreen image lightbox with prev/next + Escape.
 * Keep this as a dedicated chunk so catalog/community routes stay light.
 */
export const ImageLightbox = component$<Props>((props) => {
  const { locale } = useI18n();
  const openAt = props.index.value;
  const open = openAt != null && props.images.length > 0;
  const safeIndex =
    openAt == null
      ? 0
      : Math.max(0, Math.min(openAt, props.images.length - 1));
  const current = props.images[safeIndex];
  const multi = props.images.length > 1;

  const close$ = $(() => {
    props.index.value = null;
  });

  const prev$ = $(() => {
    if (props.images.length < 2 || props.index.value == null) return;
    const i = props.index.value;
    props.index.value = (i - 1 + props.images.length) % props.images.length;
  });

  const next$ = $(() => {
    if (props.images.length < 2 || props.index.value == null) return;
    const i = props.index.value;
    props.index.value = (i + 1) % props.images.length;
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track, cleanup }) => {
    const idx = track(() => props.index.value);
    if (idx == null) {
      document.body.style.overflow = "";
      return;
    }
    document.body.style.overflow = "hidden";

    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        props.index.value = null;
      } else if (e.key === "ArrowLeft") {
        e.preventDefault();
        const i = props.index.value;
        if (i == null || props.images.length < 2) return;
        props.index.value = (i - 1 + props.images.length) % props.images.length;
      } else if (e.key === "ArrowRight") {
        e.preventDefault();
        const i = props.index.value;
        if (i == null || props.images.length < 2) return;
        props.index.value = (i + 1) % props.images.length;
      }
    };
    window.addEventListener("keydown", onKey);
    cleanup(() => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    });
  });

  if (!open || !current) {
    return null;
  }

  return (
    <div
      class="image-lightbox"
      role="dialog"
      aria-modal="true"
      aria-label={tStatic(locale, "a11y.lightbox")}
    >
      <button
        type="button"
        class="image-lightbox__backdrop"
        aria-label={tStatic(locale, "a11y.close")}
        onClick$={close$}
      />
      <div class="image-lightbox__stage">
        <button
          type="button"
          class="image-lightbox__close"
          aria-label={tStatic(locale, "a11y.close")}
          onClick$={close$}
        >
          ×
        </button>
        {multi ? (
          <button
            type="button"
            class="image-lightbox__nav image-lightbox__nav--prev"
            aria-label={tStatic(locale, "a11y.lightboxPrev")}
            onClick$={prev$}
          >
            ‹
          </button>
        ) : null}
        <figure class="image-lightbox__figure">
          <img
            src={current.src}
            alt={current.alt || ""}
            width={1600}
            height={1200}
          />
          {current.alt ? <figcaption>{current.alt}</figcaption> : null}
        </figure>
        {multi ? (
          <button
            type="button"
            class="image-lightbox__nav image-lightbox__nav--next"
            aria-label={tStatic(locale, "a11y.lightboxNext")}
            onClick$={next$}
          >
            ›
          </button>
        ) : null}
        {multi ? (
          <p class="image-lightbox__count" aria-live="polite">
            {safeIndex + 1} / {props.images.length}
          </p>
        ) : null}
      </div>
    </div>
  );
});
