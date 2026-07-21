import { component$ } from "@builder.io/qwik";
import { tStatic, useI18n } from "~/lib/i18n/context";

export type HomeVideoSource = "self" | "youtube" | "vimeo";

interface HomeVideoProps {
  source?: HomeVideoSource | string;
  src: string;
  embedUrl?: string | null;
  poster?: string;
  title?: string;
}

/** Homepage video from GET /homepage — YouTube, Vimeo embed, or self-hosted file. */
export const HomeVideo = component$<HomeVideoProps>(({ source = "self", src, embedUrl, poster, title }) => {
  const { locale } = useI18n();
  const kind = source === "youtube" || source === "vimeo" ? source : "self";
  const iframeSrc = (embedUrl || "").trim();
  const fileSrc = src.trim();
  const label = (title || "").trim() || tStatic(locale, "home.videoAria");

  if (kind !== "self") {
    if (!iframeSrc) {
      return null;
    }

    return (
      <section class="home-video" aria-label={label}>
        <div class="home-video__frame home-video__frame--embed">
          <iframe
            class="home-video__player home-video__player--embed"
            src={iframeSrc}
            title={label}
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowFullscreen
            referrerPolicy="strict-origin-when-cross-origin"
          />
        </div>
      </section>
    );
  }

  if (!fileSrc) {
    return null;
  }

  return (
    <section class="home-video" aria-label={label}>
      <div class="home-video__frame">
        <video
          class="home-video__player"
          controls
          playsInline
          preload="metadata"
          poster={poster || undefined}
          src={fileSrc}
        />
      </div>
    </section>
  );
});
