import assert from "node:assert/strict";
import { beforeEach, test } from "node:test";
import { cachedSsr, clearSsrCache } from "../src/lib/ssr-shell-cache.ts";

beforeEach(() => clearSsrCache());

function countingLoader<T>(...results: Array<T | Error>) {
  let calls = 0;
  const load = async (): Promise<T> => {
    const next = results[Math.min(calls, results.length - 1)];
    calls += 1;
    if (next instanceof Error) {
      throw next;
    }
    return next;
  };
  return { load, calls: () => calls };
}

test("serves fresh hits without reloading", async () => {
  const loader = countingLoader("a");
  assert.equal(await cachedSsr("k", loader.load), "a");
  assert.equal(await cachedSsr("k", loader.load), "a");
  assert.equal(loader.calls(), 1);
});

test("concurrent misses share one in-flight load", async () => {
  const loader = countingLoader("a");
  const results = await Promise.all(Array.from({ length: 20 }, () => cachedSsr("k", loader.load)));
  assert.deepEqual(new Set(results), new Set(["a"]));
  assert.equal(loader.calls(), 1);
});

test("serves the last good value when a refresh fails", async () => {
  const loader = countingLoader<string>("good", new Error("POS down"));
  assert.equal(await cachedSsr("k", loader.load, { ttlMs: 0 }), "good");
  assert.equal(await cachedSsr("k", loader.load, { ttlMs: 0 }), "good");
  assert.equal(loader.calls(), 2);
});

test("rethrows and caches nothing when there is no previous value", async () => {
  const loader = countingLoader<string>(new Error("POS down"), "recovered");
  await assert.rejects(cachedSsr("k", loader.load), /POS down/);
  assert.equal(await cachedSsr("k", loader.load), "recovered");
});

test("degraded results never replace a good value", async () => {
  const isDegraded = (v: { ok: boolean }) => !v.ok;
  const loader = countingLoader({ ok: true }, { ok: false });
  assert.deepEqual(await cachedSsr("k", loader.load, { ttlMs: 0, isDegraded }), { ok: true });
  assert.deepEqual(await cachedSsr("k", loader.load, { ttlMs: 0, isDegraded }), { ok: true });
});

test("degraded results are returned but not cached when nothing better exists", async () => {
  const isDegraded = (v: { ok: boolean }) => !v.ok;
  const loader = countingLoader({ ok: false }, { ok: true });
  assert.deepEqual(await cachedSsr("k", loader.load, { isDegraded }), { ok: false });
  assert.deepEqual(await cachedSsr("k", loader.load, { isDegraded }), { ok: true });
  assert.equal(loader.calls(), 2);
});

test("expired values are served immediately while one background load refreshes them", async () => {
  const loader = countingLoader("old", "new");
  assert.equal(await cachedSsr("k", loader.load, { ttlMs: 0 }), "old");
  const results = await Promise.all(Array.from({ length: 5 }, () => cachedSsr("k", loader.load, { ttlMs: 0 })));
  assert.deepEqual(new Set(results), new Set(["old"]));
  assert.equal(loader.calls(), 2);
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(await cachedSsr("k", loader.load, { ttlMs: 60_000 }), "new");
});

test("a failed refresh backs off before retrying upstream", async () => {
  const loader = countingLoader<string>("good", new Error("POS down"), "recovered");
  await cachedSsr("k", loader.load, { ttlMs: 0 });
  await cachedSsr("k", loader.load, { ttlMs: 0, backoffMs: 60_000 });
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(await cachedSsr("k", loader.load, { ttlMs: 0, backoffMs: 60_000 }), "good");
  assert.equal(loader.calls(), 2);
});

test("a degraded refresh backs off before retrying upstream", async () => {
  const isDegraded = (v: { ok: boolean }) => !v.ok;
  const loader = countingLoader({ ok: true }, { ok: false }, { ok: true });
  await cachedSsr("k", loader.load, { ttlMs: 0, isDegraded });
  await cachedSsr("k", loader.load, { ttlMs: 0, backoffMs: 60_000, isDegraded });
  await new Promise((resolve) => setImmediate(resolve));
  await cachedSsr("k", loader.load, { ttlMs: 0, backoffMs: 60_000, isDegraded });
  assert.equal(loader.calls(), 2);
});

test("stale values expire after staleMs", async () => {
  const loader = countingLoader<string>("good", new Error("POS down"));
  await cachedSsr("k", loader.load, { ttlMs: 0, staleMs: 0 });
  await assert.rejects(cachedSsr("k", loader.load, { ttlMs: 0, staleMs: 0 }), /POS down/);
});
