import { component$, Slot, useSignal, useVisibleTask$ } from "@builder.io/qwik";

/**
 * Homepage section stack: consistent vertical rhythm + scroll-in reveals.
 * Elements marked with `data-home-reveal` get `.is-inview` once visible.
 */
export const HomeSections = component$(() => {
  const rootRef = useSignal<HTMLElement>();

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    const root = rootRef.value;
    if (!root || typeof IntersectionObserver === "undefined") {
      return;
    }

    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const targets = Array.from(root.querySelectorAll<HTMLElement>("[data-home-reveal]"));

    if (reduceMotion) {
      for (const el of targets) {
        el.classList.add("is-inview");
      }
      return;
    }

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) {
            continue;
          }
          entry.target.classList.add("is-inview");
          observer.unobserve(entry.target);
        }
      },
      { threshold: 0.12, rootMargin: "0px 0px -4% 0px" },
    );

    for (const el of targets) {
      observer.observe(el);
    }

    cleanup(() => observer.disconnect());
  });

  return (
    <div class="home-sections" ref={rootRef}>
      <Slot />
    </div>
  );
});
