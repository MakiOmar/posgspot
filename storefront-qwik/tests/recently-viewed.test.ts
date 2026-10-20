import assert from "node:assert/strict";
import { test } from "node:test";
import { parseStoredRecentlyViewed, RECENTLY_VIEWED_TTL_MS } from "../src/lib/recently-viewed.ts";

const NOW = 1_800_000_000_000;

test("keeps entries viewed within the TTL", () => {
  const raw = JSON.stringify([{ id: 1, name: "Fresh", viewed_at: NOW - 1000 }]);
  assert.deepEqual(
    parseStoredRecentlyViewed(raw, NOW).map((item) => item.id),
    [1],
  );
});

test("drops entries older than the TTL and entries without a timestamp", () => {
  const raw = JSON.stringify([
    { id: 1, name: "Expired", viewed_at: NOW - RECENTLY_VIEWED_TTL_MS - 1 },
    { id: 2, name: "Legacy" },
    { id: 3, name: "Fresh", viewed_at: NOW },
  ]);
  assert.deepEqual(
    parseStoredRecentlyViewed(raw, NOW).map((item) => item.id),
    [3],
  );
});

test("returns [] for corrupt data", () => {
  assert.deepEqual(parseStoredRecentlyViewed("{not json", NOW), []);
  assert.deepEqual(parseStoredRecentlyViewed(null, NOW), []);
});
