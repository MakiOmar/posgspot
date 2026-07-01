import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import type { AboutTimelineEntry } from "~/lib/about-timeline";

interface AboutTimelineProps {
  items: AboutTimelineEntry[];
}

export const AboutTimeline = component$<AboutTimelineProps>(({ items }) => {
  const sectionRef = useSignal<HTMLElement>();
  const progress = useSignal(0);
  const visibleRows = useSignal<Record<number, boolean>>({});

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    const section = sectionRef.value;
    if (!section) {
      return;
    }

    const rowEls = Array.from(section.querySelectorAll<HTMLElement>("[data-timeline-row]"));

    const updateProgress = () => {
      const rect = section.getBoundingClientRect();
      const viewportMid = window.innerHeight * 0.55;
      const traveled = viewportMid - rect.top;
      const total = Math.max(section.offsetHeight, 1);
      const ratio = Math.min(Math.max(traveled / total, 0), 1);
      progress.value = ratio * 100;
    };

    const rowObserver = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          const index = Number((entry.target as HTMLElement).dataset.timelineRow);
          if (Number.isNaN(index)) {
            continue;
          }
          visibleRows.value = {
            ...visibleRows.value,
            [index]: entry.isIntersecting,
          };
        }
      },
      { rootMargin: "-10% 0px -20% 0px", threshold: 0.2 },
    );

    for (const row of rowEls) {
      rowObserver.observe(row);
    }

    window.addEventListener("scroll", updateProgress, { passive: true });
    window.addEventListener("resize", updateProgress);
    updateProgress();

    cleanup(() => {
      rowObserver.disconnect();
      window.removeEventListener("scroll", updateProgress);
      window.removeEventListener("resize", updateProgress);
    });
  });

  return (
    <div class="about-timeline-v2" ref={sectionRef}>
      <div class="about-timeline-axis" aria-hidden="true">
        <div
          class="about-timeline-progress"
          style={{ height: `${progress.value}%` }}
        />
      </div>

      <ol class="about-timeline-rows">
        {items.map((item, index) => {
          const isEven = index % 2 === 0;
          const isVisible = visibleRows.value[index] === true;

          const media = (
            <div class="about-timeline-media">
              {item.image ? (
                <img
                  src={item.image}
                  alt={item.imageAlt || item.title}
                  width={520}
                  height={340}
                  loading="lazy"
                />
              ) : (
                <div class="about-timeline-placeholder">
                  <span>{item.year}</span>
                </div>
              )}
            </div>
          );

          const copy = (
            <div class="about-timeline-copy">
              <span class="about-timeline-year-label">{item.year}</span>
              <h3 class="about-timeline-item-title">{item.title}</h3>
              <p>{item.text}</p>
            </div>
          );

          return (
            <li
              key={item.year}
              class={`about-timeline-row${isVisible ? " about-timeline-row--visible" : ""}`}
              data-timeline-row={index}
            >
              <div class="about-timeline-slot about-timeline-slot--start">
                {isEven ? media : copy}
              </div>
              <div class="about-timeline-marker-wrap" aria-hidden="true">
                <span class="about-timeline-marker" />
              </div>
              <div class="about-timeline-slot about-timeline-slot--end">
                {isEven ? copy : media}
              </div>
            </li>
          );
        })}
      </ol>
    </div>
  );
});
