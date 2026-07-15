import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { Link } from "@builder.io/qwik-city";
import { HOME_HERO_SLIDES } from "~/lib/home-demo";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";

/**
 * Full-bleed homepage hero carousel (demo slides from live site images).
 */
export const HeroSlider = component$(() => {
  const { locale } = useI18n();
  const index = useSignal(0);
  const slides = HOME_HERO_SLIDES;

  // Auto-advance slides on the client only.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    const timer = setInterval(() => {
      index.value = (index.value + 1) % slides.length;
    }, 6000);
    cleanup(() => clearInterval(timer));
  });

  const slide = slides[index.value] ?? slides[0];

  return (
    <section class="home-hero-slider" aria-roledescription="carousel" aria-label={tStatic(locale, "home.heroAria")}>
      {slides.map((item, i) => (
        <div
          key={item.id}
          class={["home-hero-slider__slide", i === index.value ? "is-active" : ""].join(" ")}
          aria-hidden={i !== index.value}
        >
          <img
            src={item.imageUrl}
            alt=""
            class="home-hero-slider__bg"
            width={1920}
            height={800}
            loading={i === 0 ? "eager" : "lazy"}
            fetchPriority={i === 0 ? "high" : undefined}
          />
        </div>
      ))}
      <div class="home-hero-slider__content">
        <p class="home-hero-slider__kicker">{slide.kicker}</p>
        <h1 class="home-hero-slider__title">{slide.title}</h1>
        <Link href={localePath(locale, slide.href)} class="btn btn-primary">
          {tStatic(locale, "home.shopNow")}
        </Link>
      </div>
      <div class="home-hero-slider__dots" role="tablist">
        {slides.map((item, i) => (
          <button
            key={item.id}
            type="button"
            class={["home-hero-slider__dot", i === index.value ? "is-active" : ""].join(" ")}
            aria-label={`${tStatic(locale, "home.slide")} ${i + 1}`}
            aria-selected={i === index.value}
            onClick$={() => {
              index.value = i;
            }}
          />
        ))}
      </div>
    </section>
  );
});
