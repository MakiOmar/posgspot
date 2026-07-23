import { Resource, component$, useResource$ } from "@builder.io/qwik";
import { fetchRemoteSvgMarkup, isSvgUrl } from "~/lib/remote-svg";

export interface RemoteSvgProps {
  /** Absolute URL to an .svg file (media library / uploads). */
  src: string;
  /** Applied as CSS `color` when markup uses currentColor (default). */
  color?: string;
  class?: string;
  /** When fetch/sanitize fails, render a lazy img of the same src. */
  fallbackImg?: boolean;
  /** Force currentColor fills (default true). Set false for multi-color art. */
  currentColor?: boolean;
  width?: number;
  height?: number;
}

/**
 * Loads a remote SVG file and injects sanitized markup (no CSS mask).
 * Prefer for themeable icons; use plain &lt;img&gt; for photos / multi-color logos.
 */
export const RemoteSvg = component$<RemoteSvgProps>((props) => {
  const className = props.class || "remote-svg";
  const color = props.color || "currentColor";
  const fallbackImg = props.fallbackImg !== false;
  const width = props.width ?? 48;
  const height = props.height ?? 48;

  const resource = useResource$(async ({ track }) => {
    const src = track(() => props.src);
    const tint = track(() => props.currentColor !== false);
    if (!src || !isSvgUrl(src)) {
      return "";
    }
    return fetchRemoteSvgMarkup(src, { currentColor: tint });
  });

  return (
    <Resource
      value={resource}
      onPending={() =>
        fallbackImg ? (
          <img
            class={className}
            src={props.src}
            alt=""
            width={width}
            height={height}
            loading="lazy"
          />
        ) : (
          <span class={className} aria-hidden="true" />
        )
      }
      onRejected={() =>
        fallbackImg ? (
          <img
            class={className}
            src={props.src}
            alt=""
            width={width}
            height={height}
            loading="lazy"
          />
        ) : null
      }
      onResolved={(markup) =>
        markup ? (
          <span
            class={className}
            style={{ color }}
            role="img"
            aria-hidden="true"
            dangerouslySetInnerHTML={markup}
          />
        ) : fallbackImg ? (
          <img
            class={className}
            src={props.src}
            alt=""
            width={width}
            height={height}
            loading="lazy"
          />
        ) : null
      }
    />
  );
});
