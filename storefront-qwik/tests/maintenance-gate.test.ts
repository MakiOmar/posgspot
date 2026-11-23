import assert from "node:assert/strict";
import { test } from "node:test";

/**
 * Mirrors `isForceMaintenanceAllowedApiPath` in src/lib/maintenance-gate.ts.
 * Kept inline so node:test does not need the Vite `~` alias.
 */
const FORCE_MAINTENANCE_ALLOWED_API_PATHS = [
  "/settings",
  "/phone-countries",
  "/geo/countries",
  "/customers/add",
] as const;

const FORCE_MAINTENANCE_ALLOWED_API_PREFIXES = ["/geo/states/"] as const;

function isForceMaintenanceAllowedApiPath(apiPath: string): boolean {
  const bare = (apiPath.split("?")[0] ?? apiPath).trim();
  if (
    FORCE_MAINTENANCE_ALLOWED_API_PATHS.some(
      (path) => bare === path || bare.startsWith(`${path}/`),
    )
  ) {
    return true;
  }
  return FORCE_MAINTENANCE_ALLOWED_API_PREFIXES.some((prefix) =>
    bare.startsWith(prefix),
  );
}

test("force-maintenance allowlist keeps add-customer API paths open", () => {
  for (const path of [
    "/settings",
    "/settings?shell=1",
    "/phone-countries",
    "/geo/countries",
    "/geo/states/EG",
    "/geo/states/SA",
    "/customers/add",
  ]) {
    assert.equal(isForceMaintenanceAllowedApiPath(path), true, path);
  }
});

test("force-maintenance allowlist still blocks shop API paths", () => {
  for (const path of ["/products", "/categories", "/homepage", "/cart/validate"]) {
    assert.equal(isForceMaintenanceAllowedApiPath(path), false, path);
  }
});
