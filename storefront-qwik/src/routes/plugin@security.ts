import { randomBytes } from "node:crypto";
import type { RequestHandler } from "@builder.io/qwik-city";
import { isDev } from "@builder.io/qwik/build";
import {
  buildContentSecurityPolicy,
  cspReportOnly,
  SECURITY_HEADERS,
} from "~/lib/security/csp";

function createNonce(): string {
  return randomBytes(16).toString("base64");
}

/** Apply CSP and baseline security headers to HTML document responses. */
export const onRequest: RequestHandler = ({ headers, sharedMap, url }) => {
  const path = url.pathname.toLowerCase();
  if (path.endsWith(".xml") || path.endsWith("/robots.txt")) {
    return;
  }

  for (const [name, value] of Object.entries(SECURITY_HEADERS)) {
    headers.set(name, value);
  }

  // Vite dev scripts do not receive nonces; skip CSP in development.
  if (isDev) {
    return;
  }

  const nonce = createNonce();
  sharedMap.set("@nonce", nonce);

  const policy = buildContentSecurityPolicy(nonce);
  const headerName = cspReportOnly()
    ? "Content-Security-Policy-Report-Only"
    : "Content-Security-Policy";
  headers.set(headerName, policy);
};
