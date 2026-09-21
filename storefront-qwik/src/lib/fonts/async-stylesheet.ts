/** Non-blocking stylesheet load: media=print → onload → all (see no-render-blocking rule). */

export const PLAYFAIR_GOOGLE_CSS =
  "https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap";

/** Props for a <link> that does not block first paint. */
export function asyncStylesheetLinkProps(href: string): {
  rel: "stylesheet";
  href: string;
  media: string;
  onload: string;
} {
  return {
    rel: "stylesheet",
    href,
    media: "print",
    onload: "this.media='all'",
  };
}
