import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import type { HomepageHeroSlide } from "~/lib/types";

interface HeroSliderProps {
  slides: HomepageHeroSlide[];
}

/** Prefer mobile crop ≤1023px; fall back to desktop when unset. */
function heroSlideSrc(slide: HomepageHeroSlide): { desktop: string; mobile: string } {
  const desktop = (slide.image_url || "").trim();
  const mobile = (slide.image_mobile_url || "").trim() || desktop;
  return { desktop, mobile };
}

/**
 * Full-bleed homepage hero carousel (slides from GET /homepage section settings).
 * Only the active slide mounts media so inactive heroes are not downloaded for LCP.
 */
export const HeroSlider = component$<HeroSliderProps>(({ slides }) => {
  const { locale } = useI18n();
  const index = useSignal(0);

  // Auto-advance slides on the client only.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup, track }) => {
    track(() => slides.length);
    if (slides.length < 2) {
      return;
    }
    const timer = setInterval(() => {
      index.value = (index.value + 1) % slides.length;
    }, 6000);
    cleanup(() => clearInterval(timer));
  });

  if (slides.length === 0) {
    return null;
  }

  const activeIndex = index.value;
  const slide = slides[activeIndex] ?? slides[0];

  return (
    <section
      class="home-hero-slider"
      aria-roledescription="carousel"
      aria-label={tStatic(locale, "home.heroAria")}
    >
      {slides.map((item, i) => {
        const isActive = i === activeIndex;
        const { desktop, mobile } = heroSlideSrc(item);
        const hasMobileCrop = Boolean((item.image_mobile_url || "").trim());
        return (
          <div
            key={item.id}
            class={["home-hero-slider__slide", isActive ? "is-active" : ""].join(" ")}
            aria-hidden={!isActive}
          >
            {isActive && desktop ? (
              <picture>
                {hasMobileCrop ? (
                  <source media="(max-width: 1023px)" srcset={mobile} />
                ) : null}
                <img
                  src={desktop}
                  alt=""
                  class="home-hero-slider__bg"
                  width={1920}
                  height={800}
                  sizes="100vw"
                  loading={i === 0 ? "eager" : "lazy"}
                  fetchPriority="high"
                  decoding="async"
                />
              </picture>
            ) : null}
          </div>
        );
      })}
      <div class="home-hero-slider__content">
        <p class="home-hero-slider__kicker">{slide.kicker}</p>
        <h1 class="home-hero-slider__title">{slide.title}</h1>
        <div class="home-hero-slider__actions">
          <Link href={localePath(locale, slide.href)} class="btn btn-primary home-hero-slider__btn">
            {tStatic(locale, "home.shopNow")}
          </Link>
        </div>
      </div>
      <div class="home-hero-slider__dots">
        {slides.map((item, i) => (
          <button
            key={item.id}
            type="button"
            class={["home-hero-slider__dot", i === activeIndex ? "is-active" : ""].join(" ")}
            aria-label={`${tStatic(locale, "home.slide")} ${i + 1}`}
            aria-pressed={i === activeIndex}
            onClick$={() => {
              index.value = i;
            }}
          >
            <span class="home-hero-slider__dot-pip" aria-hidden="true" />
          </button>
        ))}
      </div>
    </section>
  );
});
