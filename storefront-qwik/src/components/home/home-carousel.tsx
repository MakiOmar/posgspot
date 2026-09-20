import { $, component$, Slot, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { ChevronLeftIcon, ChevronRightIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface HomeCarouselProps {
  /** Accessible name for the carousel region. */
  label: string;
  /** Section heading text (rendered in the top toolbar). */
  title: string;
  /** Optional id for the heading (section aria-labelledby). */
  titleId?: string;
  /** Extra class on the root (e.g. variant sizing). */
  class?: string;
  /** Extra class on the sliding track (item sizing). */
  trackClass?: string;
}

/**
 * Carousel with top toolbar: title | optional `action` slot | prev/next side-by-side.
 * Advances by one item (or one “page” of items when several fit the viewport).
 */
export const HomeCarousel = component$<HomeCarouselProps>((props) => {
  const { locale } = useI18n();
  const viewportRef = useSignal<HTMLElement>();
  const trackRef = useSignal<HTMLElement>();
  const page = useSignal(0);
  const maxPage = useSignal(0);
  const stepPx = useSignal(0);

  const applyTransform$ = $((pageIndex: number, step: number) => {
    const viewport = viewportRef.value;
    const track = trackRef.value;
    if (!viewport || !track || step <= 0) {
      return;
    }
    const rtl = getComputedStyle(viewport).direction === "rtl";
    const offset = pageIndex * step;
    track.style.transform = `translate3d(${rtl ? offset : -offset}px, 0, 0)`;
  });

  const measure$ = $(() => {
    const viewport = viewportRef.value;
    const track = trackRef.value;
    if (!viewport || !track) {
      return;
    }
    const viewW = viewport.clientWidth;
    const first = track.children.item(0) as HTMLElement | null;
    if (!first || viewW <= 0) {
      maxPage.value = 0;
      stepPx.value = 0;
      applyTransform$(0, 0);
      return;
    }

    const styles = getComputedStyle(track);
    const gap = Number.parseFloat(styles.columnGap || styles.gap || "0") || 0;
    const itemW = first.getBoundingClientRect().width;
    // How many full items fit in the viewport (at least 1).
    const perPage = Math.max(1, Math.floor((viewW + gap) / (itemW + gap)));
    const step = perPage * (itemW + gap);
    const itemCount = track.children.length;
    const nextMax = Math.max(0, Math.ceil(itemCount / perPage) - 1);

    stepPx.value = step;
    maxPage.value = nextMax;
    if (page.value > nextMax) {
      page.value = nextMax;
    }
    applyTransform$(page.value, step);
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    // Defer one frame so container-query item widths have settled.
    const boot = window.requestAnimationFrame(() => {
      measure$();
    });
    const viewport = viewportRef.value;
    if (!viewport || typeof ResizeObserver === "undefined") {
      cleanup(() => window.cancelAnimationFrame(boot));
      return;
    }
    const ro = new ResizeObserver(() => {
      measure$();
    });
    ro.observe(viewport);
    if (trackRef.value) {
      ro.observe(trackRef.value);
    }
    cleanup(() => {
      window.cancelAnimationFrame(boot);
      ro.disconnect();
    });
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => page.value);
    track(() => stepPx.value);
    applyTransform$(page.value, stepPx.value);
  });

  const goPrev$ = $(() => {
    if (page.value <= 0) {
      return;
    }
    page.value -= 1;
  });

  const goNext$ = $(() => {
    if (page.value >= maxPage.value) {
      return;
    }
    page.value += 1;
  });

  const rootClass = ["home-carousel", props.class].filter(Boolean).join(" ");
  const trackClass = ["home-carousel__track", props.trackClass].filter(Boolean).join(" ");
  const canPrev = page.value > 0;
  const canNext = page.value < maxPage.value;

  return (
    <div class={rootClass} aria-roledescription="carousel" aria-label={props.label}>
      <div class="home-carousel__toolbar">
        <h2 id={props.titleId} class="home-section__title home-carousel__title">
          {props.title}
        </h2>
        <div class="home-carousel__toolbar-end">
          <div class="home-carousel__action">
            <Slot name="action" />
          </div>
          {canPrev || canNext ? (
            <div class="home-carousel__nav" role="group" aria-label={props.label}>
              {canPrev ? (
                <button
                  type="button"
                  class="home-carousel__btn home-carousel__btn--prev"
                  aria-label={tStatic(locale, "common.prev")}
                  onClick$={goPrev$}
                >
                  <ChevronLeftIcon size={18} />
                </button>
              ) : null}
              {canNext ? (
                <button
                  type="button"
                  class="home-carousel__btn home-carousel__btn--next"
                  aria-label={tStatic(locale, "common.next")}
                  onClick$={goNext$}
                >
                  <ChevronRightIcon size={18} />
                </button>
              ) : null}
            </div>
          ) : null}
        </div>
      </div>

      <div class="home-carousel__viewport" ref={viewportRef}>
        <div class={trackClass} ref={trackRef}>
          <Slot />
        </div>
      </div>
    </div>
  );
});
