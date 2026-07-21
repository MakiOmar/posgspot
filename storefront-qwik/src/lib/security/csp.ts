/**
 * Build Content-Security-Policy and related security headers for HTML responses.
 */

const DEFAULT_API_BASE = "http://localhost:8000";

/** Extra space-separated host sources for connect-src (from env). */
export function cspExtraConnectSrc(): string[] {
  const raw = (
    (typeof process !== "undefined" ? process.env.PUBLIC_CSP_EXTRA_CONNECT_SRC : undefined) ||
    import.meta.env.PUBLIC_CSP_EXTRA_CONNECT_SRC ||
    ""
  )
    .trim()
    .split(/\s+/)
    .filter(Boolean);
  return raw;
}

function apiOrigin(): string {
  const raw =
    (typeof process !== "undefined" ? process.env.PUBLIC_API_BASE : undefined) ||
    import.meta.env.PUBLIC_API_BASE ||
    DEFAULT_API_BASE;
  try {
    return new URL(raw.replace(/\/$/, "")).origin;
  } catch {
    return DEFAULT_API_BASE;
  }
}

function posWebOrigin(): string {
  const raw =
    (typeof process !== "undefined" ? process.env.PUBLIC_POS_WEB_BASE : undefined) ||
    import.meta.env.PUBLIC_POS_WEB_BASE ||
    "";
  if (!raw.trim()) {
    return "";
  }
  try {
    return new URL(raw.replace(/\/$/, "")).origin;
  } catch {
    return "";
  }
}

/** When true, send Content-Security-Policy-Report-Only instead of enforcing. */
export function cspReportOnly(): boolean {
  const raw =
    (typeof process !== "undefined" ? process.env.PUBLIC_CSP_REPORT_ONLY : undefined) ||
    import.meta.env.PUBLIC_CSP_REPORT_ONLY ||
    "";
  return raw === "1" || raw.toLowerCase() === "true";
}

export function buildContentSecurityPolicy(nonce: string): string {
  const connectSrc = ["'self'", apiOrigin(), ...cspExtraConnectSrc()];
  const posOrigin = posWebOrigin();
  if (posOrigin) {
    connectSrc.push(posOrigin);
  }

  const directives = [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "frame-ancestors 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic' https://www.atfawry.com https://atfawry.fawrystaging.com https://challenges.cloudflare.com`,
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' https://fonts.gstatic.com data:",
    "img-src 'self' data: blob: https:",
    `connect-src ${connectSrc.join(" ")} https://challenges.cloudflare.com`,
    "frame-src 'self' https://www.google.com https://maps.google.com https://www.atfawry.com https://atfawry.fawrystaging.com https://challenges.cloudflare.com https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
    "media-src 'self' https: blob:",
    "form-action 'self' https://www.atfawry.com https://atfawry.fawrystaging.com",
  ];

  return directives.join("; ");
}

export const SECURITY_HEADERS = {
  "X-Content-Type-Options": "nosniff",
  "Referrer-Policy": "strict-origin-when-cross-origin",
  "Permissions-Policy": "camera=(), microphone=(), geolocation=()",
  "X-Frame-Options": "SAMEORIGIN",
} as const;
