/**
 * Guest catalog browse + cart/validate load test (Storefront API).
 *
 * Default BASE_URL: https://pos.gamesspoteg.com/api/storefront/v1
 *
 * Smoke:
 *   k6 run load-tests/k6/scenarios/browse.js
 *
 * Load:
 *   k6 run -e SCENARIO=load --summary-export load-tests/k6/results/summary-load.json load-tests/k6/scenarios/browse.js
 *
 * Stress (capacity / throttle ceiling):
 *   k6 run -e SCENARIO=stress --summary-export load-tests/k6/results/summary-stress.json load-tests/k6/scenarios/browse.js
 *
 * Soak (under per-IP read limit):
 *   k6 run -e SCENARIO=soak --summary-export load-tests/k6/results/summary-soak.json load-tests/k6/scenarios/browse.js
 */
import { sleep } from 'k6';
import { apiGet, checkSuccess, requireBaseUrl, searchQuery } from '../lib/client.js';
import {
  fetchCategories,
  fetchProductDetail,
  fetchProducts,
  fetchSearch,
  productKey,
  validateCart,
} from '../lib/catalog.js';

const scenario = (__ENV.SCENARIO || 'smoke').toLowerCase();
const smokeDuration = __ENV.SMOKE_DURATION || '30s';

const smokeOptions = {
  vus: Number(__ENV.VUS || 2),
  duration: smokeDuration,
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800'],
    'http_req_duration{endpoint:cart_validate}': ['p(95)<1500'],
    checks: ['rate>0.95'],
  },
};

const loadOptions = {
  stages: [
    { duration: '10s', target: 10 },
    { duration: '1m', target: 30 },
    { duration: '2m', target: 50 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800'],
    'http_req_duration{endpoint:cart_validate}': ['p(95)<1500'],
    checks: ['rate>0.90'],
  },
};

/**
 * Capacity probe: intentionally exceeds per-IP read throttle to find the protective ceiling.
 * Thresholds are soft. Cart only every 5th iteration.
 *
 * Important: STOREFRONT_RATE_LIMIT_READ (default 600/min per IP) will dominate from one client.
 * For true app/DB capacity, raise that limit on POS temporarily or use distributed VUs.
 */
const stressOptions = {
  stages: [
    { duration: '20s', target: 25 },
    { duration: '40s', target: 50 },
    { duration: '1m', target: 100 },
    { duration: '1m', target: 150 },
    { duration: '1m', target: 200 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.15'],
    http_req_duration: ['p(95)<3000'],
    checks: ['rate>0.70'],
  },
};

/**
 * Sustained traffic under the default read throttle (~600 GET/min/IP).
 * ~1 VU × 7 GETs/journey stays near the legal ceiling without mass 429s.
 */
const soakOptions = {
  vus: Number(__ENV.VUS || 1),
  duration: __ENV.SOAK_DURATION || '3m',
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1000'],
    checks: ['rate>0.95'],
  },
};

export const options =
  scenario === 'stress'
    ? stressOptions
    : scenario === 'soak'
      ? soakOptions
      : scenario === 'load'
        ? loadOptions
        : smokeOptions;

export function setup() {
  requireBaseUrl();
  console.log(`SCENARIO=${scenario} BASE_URL=${requireBaseUrl()}`);
  return { startedAt: Date.now(), scenario };
}

export default function browseJourney() {
  const stress = scenario === 'stress';

  checkSuccess(apiGet('/ping', { tags: { name: 'ping', endpoint: 'ping' } }), 'ping');
  checkSuccess(apiGet('/settings', { tags: { name: 'settings', endpoint: 'settings' } }), 'settings');
  checkSuccess(apiGet('/homepage', { tags: { name: 'homepage', endpoint: 'homepage' } }), 'homepage');

  const { slug } = fetchCategories();
  const { first } = fetchProducts(slug);
  const listVariationId =
    first && first.variation_id ? Number(first.variation_id) : null;
  const idOrSlug = productKey(first);

  let pdpVariationId = null;
  if (idOrSlug) {
    const detail = fetchProductDetail(idOrSlug);
    pdpVariationId = detail.variationId;
  }

  fetchSearch(searchQuery());

  // Under stress, only cart-validate ~20% of iterations so GETs measure capacity.
  const doCart = !stress || __ITER % 5 === 0;
  if (doCart) {
    validateCart(pdpVariationId, listVariationId);
  }

  if (stress) {
    sleep(0.05 + Math.random() * 0.15);
  } else if (scenario === 'soak') {
    sleep(0.5 + Math.random() * 0.5);
  } else if (scenario === 'load') {
    sleep(0.4 + Math.random() * 0.4);
  } else {
    sleep(0.8 + Math.random() * 0.7);
  }
}
