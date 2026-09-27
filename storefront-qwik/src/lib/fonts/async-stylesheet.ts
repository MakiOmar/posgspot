/** Non-blocking stylesheet load: media=print, then activate via nonced script (CSP-safe). */

export const PLAYFAIR_GOOGLE_CSS =
  "https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap";

/** Marker attribute — RouterHead activates these with a nonce'd script (no inline onload). */
export const ASYNC_STYLESHEET_ATTR = "data-async-font";

/** Props for a <link> that does not block first paint. */
export function asyncStylesheetLinkProps(href: string): {
  rel: "stylesheet";
  href: string;
  media: string;
  [ASYNC_STYLESHEET_ATTR]: string;
} {
  return {
    rel: "stylesheet",
    href,
    media: "print",
    [ASYNC_STYLESHEET_ATTR]: "1",
  };
}

/** Inline bootstrap (must be served with CSP nonce). */
export const ASYNC_STYLESHEET_BOOTSTRAP = `(function(){try{document.querySelectorAll('link[${ASYNC_STYLESHEET_ATTR}]').forEach(function(l){function go(){l.media='all';}if(l.sheet)go();else l.addEventListener('load',go);});}catch(e){}})();`;
