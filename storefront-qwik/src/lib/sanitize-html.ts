import DOMPurify from "isomorphic-dompurify";

const ALLOWED_TAGS = [
  "p",
  "br",
  "strong",
  "b",
  "em",
  "i",
  "u",
  "ul",
  "ol",
  "li",
  "h2",
  "h3",
  "h4",
  "a",
  "span",
  "div",
  "blockquote",
];

const ALLOWED_ATTR = ["href", "title", "target", "rel", "class"];

let hooksRegistered = false;

function registerHooks(): void {
  if (hooksRegistered) {
    return;
  }
  hooksRegistered = true;

  DOMPurify.addHook("afterSanitizeAttributes", (node) => {
    if (node.tagName !== "A") {
      return;
    }
    const href = node.getAttribute("href");
    if (href && /^\s*javascript:/i.test(href)) {
      node.removeAttribute("href");
    }
    if (node.getAttribute("target") === "_blank") {
      node.setAttribute("rel", "noopener noreferrer");
    }
  });
}

/** Sanitize rich product description HTML before rendering in the DOM. */
export function sanitizeProductHtml(html: string | null | undefined): string {
  if (!html) {
    return "";
  }

  registerHooks();

  return DOMPurify.sanitize(html, {
    ALLOWED_TAGS,
    ALLOWED_ATTR,
    ALLOW_DATA_ATTR: false,
  });
}
