# Self-hosted Cairo font (optional)

When `PUBLIC_ARABIC_FONT_PROVIDER=self-hosted` is set, the storefront loads
`/fonts/cairo.css` instead of Google Fonts.

Add your files here, for example:

- `cairo.css` — `@font-face` rules pointing at local woff2 files
- `cairo-latin-400.woff2`, `cairo-arabic-400.woff2`, etc.

See Google Fonts / fontsource output for weight subsets you need (400, 600, 700).
