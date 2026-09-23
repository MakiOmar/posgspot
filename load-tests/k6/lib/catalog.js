/**
 * Catalog helpers for guest browse + cart/validate journeys.
 */
import { check } from 'k6';
import { Counter } from 'k6/metrics';
import { apiGet, apiPost, checkSuccess, parseEnvelope } from './client.js';

export const cartValidateSkips = new Counter('cart_validate_skips');
export const cartValidateRateLimits = new Counter('cart_validate_rate_limits');

/** k6 goja has no URLSearchParams — build query strings manually. */
function queryString(params) {
  const parts = [];
  Object.keys(params).forEach((key) => {
    const val = params[key];
    if (val === undefined || val === null || val === '') {
      return;
    }
    parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(String(val))}`);
  });
  return parts.join('&');
}

/**
 * Prefer numeric id when slug is missing (common on this POS catalog).
 * @param {object|null} product
 */
export function productKey(product) {
  if (!product || typeof product !== 'object') {
    return null;
  }
  const slug = product.slug;
  if (slug !== undefined && slug !== null && String(slug).trim() !== '') {
    return String(slug).trim();
  }
  if (product.id !== undefined && product.id !== null) {
    return String(product.id);
  }
  return null;
}

/**
 * @returns {{ slug: string|null, items: array }}
 */
export function fetchCategories() {
  const res = apiGet('/categories', { tags: { name: 'categories', endpoint: 'categories' } });
  const parsed = checkSuccess(res, 'categories', {
    'has data array': (p) => Array.isArray(p.data),
  });
  const items = Array.isArray(parsed.data) ? parsed.data : [];
  const first = items.find((c) => c && c.slug) || items[0] || null;
  return { slug: first && first.slug ? String(first.slug) : null, items };
}

/**
 * @param {string|null} categorySlug
 * @returns {{ products: array, first: object|null }}
 */
export function fetchProducts(categorySlug) {
  const qs = queryString({
    per_page: '24',
    category_slug: categorySlug || undefined,
  });
  const res = apiGet(`/products?${qs}`, {
    tags: { name: 'products', endpoint: 'products' },
  });
  const parsed = checkSuccess(res, 'products', {
    'has data array': (p) => Array.isArray(p.data),
  });
  const products = Array.isArray(parsed.data) ? parsed.data : [];
  // Prefer an in-stock row with a variation for cart/validate.
  const first =
    products.find((p) => p && p.in_stock && p.variation_id) ||
    products.find((p) => p && p.variation_id) ||
    products[0] ||
    null;
  return { products, first };
}

/**
 * @param {string|number} idOrSlug
 * @returns {{ product: object|null, variationId: number|null }}
 */
export function fetchProductDetail(idOrSlug) {
  const key = encodeURIComponent(String(idOrSlug));
  const res = apiGet(`/products/${key}`, {
    tags: { name: 'product_detail', endpoint: 'product_detail' },
  });
  const parsed = checkSuccess(res, 'product_detail');
  const product = parsed.data && typeof parsed.data === 'object' ? parsed.data : null;
  return { product, variationId: pickVariationId(product) };
}

/**
 * Prefer first in-stock variation id from PDP; else any variation id; else list summary field.
 * @param {object|null} product
 */
export function pickVariationId(product) {
  if (!product || typeof product !== 'object') {
    return null;
  }
  const variations = Array.isArray(product.variations) ? product.variations : [];
  const inStock = variations.find((v) => v && v.id && v.in_stock !== false);
  if (inStock && inStock.id) {
    return Number(inStock.id);
  }
  if (variations[0] && variations[0].id) {
    return Number(variations[0].id);
  }
  if (product.variation_id) {
    return Number(product.variation_id);
  }
  return null;
}

/**
 * @param {string} q
 */
export function fetchSearch(q) {
  const qs = queryString({ q: q || 'dual', limit: '8' });
  const res = apiGet(`/search?${qs}`, {
    tags: { name: 'search', endpoint: 'search' },
  });
  return checkSuccess(res, 'search');
}

/**
 * Cart revalidate with resolve:true (inspect lines; does not create an order).
 * Skips softly when variationId is missing (sparse catalogs).
 *
 * @param {number|null} variationId
 * @param {number|null} [fallbackVariationId] — e.g. from product list summary
 * @returns {'ok'|'skip'|'fail'}
 */
export function validateCart(variationId, fallbackVariationId = null) {
  const id = variationId || fallbackVariationId;
  if (!id || !Number.isFinite(Number(id)) || Number(id) <= 0) {
    cartValidateSkips.add(1);
    check(null, { 'cart_validate skipped (no variation)': () => true });
    return 'skip';
  }

  const res = apiPost(
    '/cart/validate',
    {
      items: [{ variation_id: Number(id), quantity: 1 }],
      resolve: true,
    },
    {
      tags: { name: 'cart_validate', endpoint: 'cart_validate' },
      // Live POS write throttle (default 120/min) — do not fail the run on brief 429s.
      expectedStatuses: [200, 429],
    }
  );

  if (res.status === 429) {
    cartValidateRateLimits.add(1);
    check(null, { 'cart_validate soft skip (429)': () => true });
    return 'skip';
  }

  const parsed = parseEnvelope(res);
  check(res, {
    'cart_validate status 2xx': (r) => r.status >= 200 && r.status < 300,
    'cart_validate success true': () => parsed.ok === true,
  });

  return parsed.ok ? 'ok' : 'fail';
}
