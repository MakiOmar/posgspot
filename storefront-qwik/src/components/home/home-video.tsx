import { component$ } from "@builder.io/qwik";
import { tStatic, useI18n } from "~/lib/i18n/context";

interface HomeVideoProps {
  src: string;
  poster?: string;
}

/** Homepage video from GET /homepage section settings. */
export const HomeVideo = component$<HomeVideoProps>(({ src, poster }) => {
  const { locale } = useI18n();

  if (!src) {
    return null;
  }

  return (
    <section class="home-video" aria-label={tStatic(locale, "home.videoAria")}>
      <div class="home-video__frame">
        <video
          class="home-video__player"
          controls
          playsInline
          preload="metadata"
          poster={poster || undefined}
          src={src}
        />
      </div>
    </section>
  );
});
