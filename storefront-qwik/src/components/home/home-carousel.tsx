import { $, component$, Slot, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { ChevronLeftIcon, ChevronRightIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface HomeCarouselProps {
  /** Accessible name for the carousel region. */
  label: string;
  /** Extra class on the root (e.g. variant sizing). */
  class?: string;
  /** Extra class on the sliding track (item sizing). */
  trackClass?: string;
}

/**
 * Page-based carousel: overflow hidden + prev/next arrows (no scrollbar).
 * Slides by one viewport width so items feel like real slides.
 */
export const HomeCarousel = component$<HomeCarouselProps>((props) => {
  const { locale } = useI18n();
  const rootRef = useSignal<HTMLElement>();
  const page = useSignal(0);
  const maxPage = useSignal(0);

  const measure$ = $(() => {
    const root = rootRef.value;
    if (!root) {
      return;
    }
    const viewport = root.querySelector<HTMLElement>(".home-carousel__viewport");
    const track = root.querySelector<HTMLElement>(".home-carousel__track");
    if (!viewport || !track) {
      return;
    }
    const viewW = viewport.clientWidth;
    const trackW = track.scrollWidth;
    const nextMax = viewW > 0 ? Math.max(0, Math.ceil(trackW / viewW) - 1) : 0;
    maxPage.value = nextMax;
    if (page.value > nextMax) {
      page.value = nextMax;
    }
    const rtl = getComputedStyle(root).direction === "rtl";
    const offset = page.value * viewW;
    track.style.transform = `translate3d(${rtl ? offset : -offset}px, 0, 0)`;
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    measure$();
    const root = rootRef.value;
    if (!root || typeof ResizeObserver === "undefined") {
      return;
    }
    const ro = new ResizeObserver(() => {
      measure$();
    });
    ro.observe(root);
    cleanup(() => ro.disconnect());
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => page.value);
    measure$();
  });

  const goPrev$ = $(() => {
    page.value = Math.max(0, page.value - 1);
  });

  const goNext$ = $(() => {
    page.value = Math.min(maxPage.value, page.value + 1);
  });

  const rootClass = ["home-carousel", props.class].filter(Boolean).join(" ");
  const trackClass = ["home-carousel__track", props.trackClass].filter(Boolean).join(" ");

  return (
    <div
      class={rootClass}
      ref={rootRef}
      aria-roledescription="carousel"
      aria-label={props.label}
    >
      <button
        type="button"
        class="home-carousel__btn home-carousel__btn--prev"
        aria-label={tStatic(locale, "common.prev")}
        disabled={page.value <= 0 || maxPage.value <= 0}
        onClick$={goPrev$}
      >
        <ChevronLeftIcon size={20} />
      </button>

      <div class="home-carousel__viewport">
        <div class={trackClass}>
          <Slot />
        </div>
      </div>

      <button
        type="button"
        class="home-carousel__btn home-carousel__btn--next"
        aria-label={tStatic(locale, "common.next")}
        disabled={page.value >= maxPage.value || maxPage.value <= 0}
        onClick$={goNext$}
      >
        <ChevronRightIcon size={20} />
      </button>
    </div>
  );
});
