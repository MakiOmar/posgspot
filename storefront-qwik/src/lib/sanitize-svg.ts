import DOMPurify from "isomorphic-dompurify";

const MAX_SVG_BYTES = 64 * 1024;

let svgHooksRegistered = false;

function registerSvgHooks(): void {
  if (svgHooksRegistered) {
    return;
  }
  svgHooksRegistered = true;

  DOMPurify.addHook("uponSanitizeElement", (node, data) => {
    if (data.tagName === "script" || data.tagName === "foreignObject") {
      if (node.parentNode) {
        node.parentNode.removeChild(node);
      }
    }
  });
}

/** Strip dangerous SVG and keep a drawable subset for inline icons. */
export function sanitizeSvgMarkup(raw: string | null | undefined): string {
  if (!raw) {
    return "";
  }

  const trimmed = raw.trim();
  if (!trimmed || trimmed.length > MAX_SVG_BYTES) {
    return "";
  }

  registerSvgHooks();

  const cleaned = DOMPurify.sanitize(trimmed, {
    USE_PROFILES: { svg: true, svgFilters: true },
    ADD_TAGS: ["use"],
    ADD_ATTR: ["xlink:href", "href"],
    ALLOW_UNKNOWN_PROTOCOLS: false,
    ALLOW_DATA_ATTR: false,
  });

  if (!cleaned || !/<svg[\s>]/i.test(cleaned)) {
    return "";
  }

  return cleaned;
}

export { MAX_SVG_BYTES };

/**
 * Prefer currentColor so the host element `color` (icon_color) tints monochrome icons.
 * Leaves fill/stroke="none" alone.
 */
export function applySvgCurrentColor(svg: string): string {
  return svg
    .replace(/\sfill="(?!none")[^"]*"/gi, ' fill="currentColor"')
    .replace(/\sfill='(?!none')[^']*'/gi, " fill='currentColor'")
    .replace(/\sstroke="(?!none")[^"]*"/gi, ' stroke="currentColor"')
    .replace(/\sstroke='(?!none')[^']*'/gi, " stroke='currentColor'");
}

/** Ensure root svg scales inside a sized wrapper. */
export function normalizeSvgRoot(svg: string): string {
  return svg.replace(/<svg\b([^>]*)>/i, (_match, attrs: string) => {
    let next = String(attrs);
    if (!/\bwidth=/i.test(next)) {
      next += ' width="100%"';
    } else {
      next = next.replace(/\bwidth=(["'])[^"']*\1/i, 'width="100%"');
    }
    if (!/\bheight=/i.test(next)) {
      next += ' height="100%"';
    } else {
      next = next.replace(/\bheight=(["'])[^"']*\1/i, 'height="100%"');
    }
    if (!/\bpreserveAspectRatio=/i.test(next)) {
      next += ' preserveAspectRatio="xMidYMid meet"';
    }
    return `<svg${next}>`;
  });
}

/** Sanitize + prepare markup for themed inline icons. */
export function prepareInlineSvg(
  raw: string | null | undefined,
  options: { currentColor?: boolean } = {},
): string {
  let svg = sanitizeSvgMarkup(raw);
  if (!svg) {
    return "";
  }
  if (options.currentColor !== false) {
    svg = applySvgCurrentColor(svg);
  }
  return normalizeSvgRoot(svg);
}
