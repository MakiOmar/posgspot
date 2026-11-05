/// <reference types="node" />
import assert from "node:assert/strict";
import { test } from "node:test";
import {
  RECENTLY_VIEWED_DISPLAY,
  RECENTLY_VIEWED_MAX,
  RECENTLY_VIEWED_TTL_MS,
  parseStoredRecentlyViewed,
  prependRecentlyViewed,
  recentlyViewedForDisplay,
  recentlyViewedStorageKey,
  type StoredRecentlyViewed,
} from "../src/lib/recently-viewed.ts";

const NOW = 1_800_000_000_000;

function entry(id: number, viewedAt = NOW): StoredRecentlyViewed {
  return { id, name: `Product ${id}`, slug: `p-${id}`, viewed_at: viewedAt };
}

test("storage key matches the website per-locale key", () => {
  assert.equal(recentlyViewedStorageKey("ar"), "gs-recently-viewed-ar-v2");
});

test("parse drops corrupt payloads, invalid rows, and expired entries", () => {
  assert.deepEqual(parseStoredRecentlyViewed(null, NOW), []);
  assert.deepEqual(parseStoredRecentlyViewed("{not json", NOW), []);
  assert.deepEqual(parseStoredRecentlyViewed('{"id":1}', NOW), []);

  const raw = JSON.stringify([
    entry(1),
    { id: 2, name: "No timestamp" },
    entry(3, NOW - RECENTLY_VIEWED_TTL_MS),
    entry(4, NOW - RECENTLY_VIEWED_TTL_MS + 1),
  ]);
  assert.deepEqual(
    parseStoredRecentlyViewed(raw, NOW).map((item) => item.id),
    [1, 4],
  );
});

test("prepend dedupes by id, puts the newest first, and caps the history", () => {
  const existing = Array.from({ length: RECENTLY_VIEWED_MAX }, (_, i) => entry(i + 1));
  const next = prependRecentlyViewed(existing, { id: 5, name: "Product 5", slug: "p-5" }, NOW + 1);
  assert.equal(next.length, RECENTLY_VIEWED_MAX);
  assert.equal(next[0].id, 5);
  assert.equal(next[0].viewed_at, NOW + 1);
  assert.equal(next.filter((item) => item.id === 5).length, 1);

  const added = prependRecentlyViewed(existing, { id: 99, name: "New", slug: null }, NOW);
  assert.equal(added.length, RECENTLY_VIEWED_MAX);
  assert.equal(added[0].id, 99);
  assert.equal(added.some((item) => item.id === RECENTLY_VIEWED_MAX), false);
});

test("display excludes the current product, limits, and strips timestamps", () => {
  const stored = Array.from({ length: 10 }, (_, i) => entry(i + 1));
  const shown = recentlyViewedForDisplay(stored, { excludeProductId: 1 });
  assert.equal(shown.length, RECENTLY_VIEWED_DISPLAY);
  assert.equal(shown[0].id, 2);
  assert.equal("viewed_at" in shown[0], false);
});
