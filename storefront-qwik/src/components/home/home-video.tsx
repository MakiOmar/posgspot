import { component$ } from "@builder.io/qwik";
import { HOME_VIDEO } from "~/lib/home-demo";
import { tStatic, useI18n } from "~/lib/i18n/context";

/** Hosted homepage video (demo trailer from live site). */
export const HomeVideo = component$(() => {
  const { locale } = useI18n();

  return (
    <section class="home-video" aria-label={tStatic(locale, "home.videoAria")}>
      <div class="home-video__frame">
        <video
          class="home-video__player"
          controls
          playsInline
          preload="metadata"
          poster={HOME_VIDEO.poster}
          src={HOME_VIDEO.src}
        />
      </div>
    </section>
  );
});
