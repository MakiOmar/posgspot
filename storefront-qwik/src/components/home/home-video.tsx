import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { tStatic, useI18n } from "~/lib/i18n/context";

export type HomeVideoSource = "self" | "youtube" | "vimeo";

interface HomeVideoProps {
  source?: HomeVideoSource | string;
  src: string;
  embedUrl?: string | null;
  poster?: string;
  title?: string;
}

/** Extract a YouTube video id from watch / embed / short URLs. */
function youtubeIdFromUrl(raw: string): string | null {
  const url = raw.trim();
  if (!url) {
    return null;
  }
  try {
    const u = new URL(url);
    const host = u.hostname.replace(/^www\./, "");
    if (host === "youtu.be") {
      const id = u.pathname.split("/").filter(Boolean)[0];
      return id || null;
    }
    if (host === "youtube.com" || host === "youtube-nocookie.com" || host.endsWith(".youtube.com")) {
      const v = u.searchParams.get("v");
      if (v) {
        return v;
      }
      const parts = u.pathname.split("/").filter(Boolean);
      const embedIdx = parts.indexOf("embed");
      if (embedIdx >= 0 && parts[embedIdx + 1]) {
        return parts[embedIdx + 1];
      }
      const shortsIdx = parts.indexOf("shorts");
      if (shortsIdx >= 0 && parts[shortsIdx + 1]) {
        return parts[shortsIdx + 1];
      }
    }
  } catch {
    /* ignore */
  }
  return null;
}

/** Prefer privacy-enhanced embed host; keep query string (e.g. rel=0). */
function privacyEmbedUrl(raw: string, videoId: string | null): string {
  const trimmed = raw.trim();
  if (!videoId) {
    return trimmed;
  }
  try {
    const u = new URL(trimmed);
    const qs = u.search ? u.search : "";
    return `https://www.youtube-nocookie.com/embed/${videoId}${qs}`;
  } catch {
    return `https://www.youtube-nocookie.com/embed/${videoId}`;
  }
}

function withAutoplay(embedSrc: string): string {
  return embedSrc.includes("?") ? `${embedSrc}&autoplay=1` : `${embedSrc}?autoplay=1`;
}

/**
 * Homepage video from GET /homepage — YouTube, Vimeo embed, or self-hosted file.
 * Embeds stay as a poster facade until the section is near the viewport (or the
 * visitor clicks play). The YouTube/Vimeo iframe is not requested until then.
 */
export const HomeVideo = component$<HomeVideoProps>(
  ({ source = "self", src, embedUrl, poster, title }) => {
    const { locale } = useI18n();
    const rootRef = useSignal<HTMLElement>();
    /** Mount third-party iframe (viewport intersection or play click). */
    const loadEmbed = useSignal(false);
    /** Play was clicked — request autoplay when the iframe mounts. */
    const wantAutoplay = useSignal(false);
    const kind = source === "youtube" || source === "vimeo" ? source : "self";
    const rawEmbed = (embedUrl || "").trim();
    const fileSrc = src.trim();
    const label = (title || "").trim() || tStatic(locale, "home.videoAria");
    const playLabel = tStatic(locale, "home.playVideo");

    useVisibleTask$(({ cleanup }) => {
      if (kind === "self") {
        return;
      }
      const el = rootRef.value;
      if (!el) {
        return;
      }
      if (typeof IntersectionObserver === "undefined") {
        return;
      }
      const io = new IntersectionObserver(
        (entries) => {
          if (entries.some((entry) => entry.isIntersecting)) {
            loadEmbed.value = true;
            io.disconnect();
          }
        },
        { root: null, rootMargin: "200px 0px", threshold: 0.01 },
      );
      io.observe(el);
      cleanup(() => io.disconnect());
    });

    if (kind !== "self") {
      if (!rawEmbed) {
        return null;
      }

      const ytId = kind === "youtube" ? youtubeIdFromUrl(rawEmbed) : null;
      const iframeSrc =
        kind === "youtube" ? privacyEmbedUrl(rawEmbed, ytId) : rawEmbed;
      const facadePoster =
        (poster || "").trim() ||
        (ytId ? `https://i.ytimg.com/vi/${ytId}/hqdefault.jpg` : "");
      const playerSrc = wantAutoplay.value ? withAutoplay(iframeSrc) : iframeSrc;

      return (
        <section ref={rootRef} class="home-video" aria-label={label}>
          <div class="home-video__frame home-video__frame--embed">
            {loadEmbed.value ? (
              <iframe
                class="home-video__player home-video__player--embed"
                src={playerSrc}
                title={label}
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowFullscreen
                loading="lazy"
                referrerPolicy="strict-origin-when-cross-origin"
              />
            ) : (
              <button
                type="button"
                class="home-video__facade"
                aria-label={playLabel}
                onClick$={() => {
                  wantAutoplay.value = true;
                  loadEmbed.value = true;
                }}
              >
                {facadePoster ? (
                  <img
                    class="home-video__facade-img"
                    src={facadePoster}
                    alt=""
                    width={1280}
                    height={720}
                    sizes="(max-width: 960px) 100vw, 960px"
                    loading="lazy"
                    decoding="async"
                  />
                ) : (
                  <span class="home-video__facade-fallback" aria-hidden="true" />
                )}
                <span class="home-video__facade-play" aria-hidden="true">
                  <svg viewBox="0 0 68 48" width="68" height="48" focusable="false">
                    <path
                      d="M66.5 7.7c-.8-2.9-2.5-5.4-5.4-6.2C55.9.1 34 0 34 0S12.1.1 6.9 1.5C4 2.3 2.3 4.8 1.5 7.7 0 13 0 24 0 24s0 11 1.5 16.3c.8 2.9 2.5 5.4 5.4 6.2C12.1 47.9 34 48 34 48s21.9-.1 27.1-1.5c2.9-.8 4.6-3.3 5.4-6.2C68 35 68 24 68 24s0-11-1.5-16.3z"
                      fill="currentColor"
                      fill-opacity="0.85"
                    />
                    <path d="M45 24 27 14v20z" fill="#fff" />
                  </svg>
                </span>
              </button>
            )}
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
  },
);
