/**
 * Mixed VPS load: Storefront HTML (Qwik) + POS Storefront API.
 *
 * Targets staging by default:
 *   SHOP_URL=https://thespotmanagment.io
 *   BASE_URL=https://pos.thespotmanagment.io/api/storefront/v1
 *
 *   k6 run -e SCENARIO=load load-tests/k6/scenarios/vps-mixed.js
 *   k6 run -e SCENARIO=stress --summary-export load-tests/k6/results/summary-vps-stress.json load-tests/k6/scenarios/vps-mixed.js
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { apiGet, checkSuccess, requireBaseUrl, searchQuery } from '../lib/client.js';
import {
  fetchCategories,
  fetchProductDetail,
  fetchProducts,
  fetchSearch,
  productKey,
  validateCart,
} from '../lib/catalog.js';

const scenario = (__ENV.SCENARIO || 'load').toLowerCase();
const shopBase = (__ENV.SHOP_URL || 'https://thespotmanagment.io').replace(/\/+$/, '');
const accountsBase = (__ENV.ACCOUNTS_URL || 'https://accounts.thespotmanagment.io').replace(
  /\/+$/,
  ''
);

const loadOptions = {
  stages: [
    { duration: '20s', target: 20 },
    { duration: '1m', target: 50 },
    { duration: '2m', target: 80 },
    { duration: '1m', target: 100 },
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<2500'],
    checks: ['rate>0.85'],
  },
};

const stressOptions = {
  stages: [
    { duration: '30s', target: 50 },
    { duration: '1m', target: 100 },
    { duration: '1m', target: 150 },
    { duration: '1m', target: 200 },
    { duration: '1m', target: 250 },
    { duration: '40s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.20'],
    http_req_duration: ['p(95)<5000'],
    checks: ['rate>0.65'],
  },
};

const smokeOptions = {
  vus: Number(__ENV.VUS || 5),
  duration: __ENV.SMOKE_DURATION || '45s',
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1500'],
    checks: ['rate>0.90'],
  },
};

export const options =
  scenario === 'stress' ? stressOptions : scenario === 'smoke' ? smokeOptions : loadOptions;

function hitShop(path, name) {
  const res = http.get(`${shopBase}${path}`, {
    headers: { Accept: 'text/html,application/xhtml+xml' },
    tags: { name, endpoint: 'shop_html', surface: 'storefront' },
  });
  check(res, {
    [`${name} status 2xx`]: (r) => r.status >= 200 && r.status < 400,
  });
  return res;
}

function hitAccounts() {
  const res = http.get(`${accountsBase}/`, {
    headers: { Accept: 'text/html,application/xhtml+xml' },
    tags: { name: 'accounts_home', endpoint: 'accounts_html', surface: 'accounts' },
  });
  check(res, {
    'accounts home 2xx': (r) => r.status >= 200 && r.status < 400,
  });
  return res;
}

export function setup() {
  requireBaseUrl();
  console.log(
    `SCENARIO=${scenario} BASE_URL=${requireBaseUrl()} SHOP_URL=${shopBase} ACCOUNTS_URL=${accountsBase}`
  );
  return { startedAt: Date.now() };
}

export default function vpsMixedJourney() {
  const stress = scenario === 'stress';

  // Qwik storefront HTML (Node)
  hitShop('/en/', 'shop_en_home');
  if (__ITER % 3 === 0) {
    hitShop('/ar/', 'shop_ar_home');
  }
  if (__ITER % 4 === 0) {
    hitShop('/en/products', 'shop_en_catalog');
  }

  // Accounts every ~8th iteration (lighter surface)
  if (__ITER % 8 === 0) {
    hitAccounts();
  }

  // POS Storefront API (Laravel + Redis cache)
  checkSuccess(apiGet('/ping', { tags: { name: 'ping', endpoint: 'ping' } }), 'ping');
  checkSuccess(apiGet('/settings', { tags: { name: 'settings', endpoint: 'settings' } }), 'settings');
  checkSuccess(apiGet('/homepage', { tags: { name: 'homepage', endpoint: 'homepage' } }), 'homepage');

  const { slug } = fetchCategories();
  const { first } = fetchProducts(slug);
  const idOrSlug = productKey(first);
  let pdpVariationId = null;
  if (idOrSlug) {
    pdpVariationId = fetchProductDetail(idOrSlug).variationId;
  }
  fetchSearch(searchQuery());

  const doCart = !stress || __ITER % 5 === 0;
  if (doCart) {
    validateCart(pdpVariationId, first && first.variation_id ? Number(first.variation_id) : null);
  }

  if (stress) {
    sleep(0.05 + Math.random() * 0.1);
  } else {
    sleep(0.25 + Math.random() * 0.35);
  }
}
