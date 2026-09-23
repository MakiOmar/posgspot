/**
 * Shared Storefront API client for k6.
 *
 * Env:
 *   BASE_URL  — default https://pos.gamesspoteg.com/api/storefront/v1
 *   LOCALE    — X-Content-Locale (default en)
 */
import http from 'k6/http';
import { check } from 'k6';

/** Default target: live POS Storefront API. Override with -e BASE_URL=… for other hosts. */
export const DEFAULT_BASE_URL = 'https://pos.gamesspoteg.com/api/storefront/v1';

export function requireBaseUrl() {
  const raw = (__ENV.BASE_URL || DEFAULT_BASE_URL).trim().replace(/\/+$/, '');
  if (!raw) {
    throw new Error(
      'BASE_URL is empty. Example: k6 run -e BASE_URL=https://pos.gamesspoteg.com/api/storefront/v1 scenarios/browse.js'
    );
  }
  return raw;
}

export function locale() {
  return (__ENV.LOCALE || 'en').trim() || 'en';
}

export function searchQuery() {
  return (__ENV.SEARCH_Q || 'dual').trim() || 'dual';
}

export function defaultHeaders() {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Content-Locale': locale(),
  };
}

function absoluteUrl(path) {
  const base = requireBaseUrl();
  return path.startsWith('http') ? path : `${base}${path.startsWith('/') ? path : `/${path}`}`;
}

/**
 * @param {string} path
 * @param {{ tags?: object }} [params]
 */
export function apiGet(path, params = {}) {
  return http.get(absoluteUrl(path), {
    headers: defaultHeaders(),
    tags: params.tags || {},
  });
}

/**
 * @param {string} path
 * @param {object|string} body
 * @param {{ tags?: object, expectedStatuses?: number[] }} [params]
 */
export function apiPost(path, body, params = {}) {
  const payload = typeof body === 'string' ? body : JSON.stringify(body);
  const opts = {
    headers: defaultHeaders(),
    tags: params.tags || {},
  };
  if (params.expectedStatuses && params.expectedStatuses.length) {
    opts.responseCallback = http.expectedStatuses(...params.expectedStatuses);
  }
  return http.post(absoluteUrl(path), payload, opts);
}

/**
 * Parse envelope; return { ok, status, body, data, meta }.
 */
export function parseEnvelope(res) {
  let body = null;
  try {
    body = res.json();
  } catch (_e) {
    body = null;
  }
  return {
    ok: res.status >= 200 && res.status < 300 && body && body.success === true,
    status: res.status,
    body,
    data: body && body.data !== undefined ? body.data : null,
    meta: body && body.meta !== undefined ? body.meta : null,
  };
}

/**
 * Assert happy-path envelope. Returns parsed result.
 */
export function checkSuccess(res, name, extra = {}) {
  const parsed = parseEnvelope(res);
  const checks = {
    [`${name} status 2xx`]: (r) => r.status >= 200 && r.status < 300,
    [`${name} success true`]: () => parsed.ok === true,
  };
  const labels = Object.keys(extra);
  for (let i = 0; i < labels.length; i += 1) {
    const label = labels[i];
    checks[`${name} ${label}`] = ((fn) => () => fn(parsed, res))(extra[label]);
  }
  check(res, checks);
  return parsed;
}
