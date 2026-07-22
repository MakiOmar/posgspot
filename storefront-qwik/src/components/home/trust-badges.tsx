import { component$ } from "@builder.io/qwik";
import type { HomepageTrustBadge } from "~/lib/types";

interface TrustBadgesProps {
  items: HomepageTrustBadge[];
}

/** Highlight phone-like digit runs (e.g. hotline) with accent color. */
function descriptionParts(text: string): Array<{ text: string; accent: boolean }> {
  const parts: Array<{ text: string; accent: boolean }> = [];
  const re = /(\d{4,})/g;
  let last = 0;
  let match: RegExpExecArray | null;
  while ((match = re.exec(text)) !== null) {
    if (match.index > last) {
      parts.push({ text: text.slice(last, match.index), accent: false });
    }
    parts.push({ text: match[1], accent: true });
    last = match.index + match[1].length;
  }
  if (last < text.length) {
    parts.push({ text: text.slice(last), accent: false });
  }
  return parts.length > 0 ? parts : [{ text, accent: false }];
}

function isSvgIcon(item: HomepageTrustBadge): boolean {
  if (item.icon_kind === "svg") {
    return true;
  }
  const url = item.icon_url || "";
  return /\.svg(\?|#|$)/i.test(url);
}

/** Homepage trust badges — SVG icons use CSS mask + icon_color; rasters use lazy img. */
export const TrustBadges = component$<TrustBadgesProps>(({ items }) => {
  if (items.length === 0) {
    return null;
  }

  return (
    <section class="home-trust-badges" aria-label="Store benefits">
      <ul class="home-trust-badges__list">
        {items.map((item) => {
          const color = item.icon_color || "#f5a623";
          const useMask = Boolean(item.icon_url) && isSvgIcon(item);

          return (
            <li key={item.id || item.title} class="home-trust-badges__item">
              {useMask ? (
                <span
                  class="home-trust-badges__icon home-trust-badges__icon--mask"
                  role="img"
                  aria-hidden="true"
                  style={{
                    backgroundColor: color,
                    WebkitMaskImage: `url("${item.icon_url}")`,
                    maskImage: `url("${item.icon_url}")`,
                  }}
                />
              ) : item.icon_url ? (
                <img
                  class="home-trust-badges__icon"
                  src={item.icon_url}
                  alt=""
                  width={48}
                  height={48}
                  loading="lazy"
                />
              ) : null}
              {item.title ? <h3 class="home-trust-badges__title">{item.title}</h3> : null}
              {item.description ? (
                <p class="home-trust-badges__desc">
                  {descriptionParts(item.description).map((part, i) =>
                    part.accent ? (
                      <span key={i} class="home-trust-badges__accent">
                        {part.text}
                      </span>
                    ) : (
                      <span key={i}>{part.text}</span>
                    ),
                  )}
                </p>
              ) : null}
            </li>
          );
        })}
      </ul>
    </section>
  );
});
