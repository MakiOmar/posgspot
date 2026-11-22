import { stripLocalePrefix } from "~/lib/i18n/paths";

/** Public routes that stay reachable while storefront maintenance mode is on. */
const MAINTENANCE_EXEMPT_BARE_PATHS = ["/add-customer"] as const;

/**
 * Storefront API paths the exempt routes need while
 * `PUBLIC_STOREFRONT_FORCE_MAINTENANCE` blocks everything else.
 */
const FORCE_MAINTENANCE_ALLOWED_API_PATHS = [
  "/settings",
  "/phone-countries",
  "/geo/countries",
  "/customers/add",
] as const;

const FORCE_MAINTENANCE_ALLOWED_API_PREFIXES = ["/geo/states/"] as const;

export function isMaintenanceExemptPath(pathname: string): boolean {
  const bare = stripLocalePrefix(pathname);
  return MAINTENANCE_EXEMPT_BARE_PATHS.some(
    (path) => bare === path || bare.startsWith(`${path}/`),
  );
}

export function isMaintenancePagePath(pathname: string): boolean {
  const bare = stripLocalePrefix(pathname);
  return bare === "/maintenance" || bare.startsWith("/maintenance/");
}

/** True when force-maintenance still allows this Storefront API path. */
export function isForceMaintenanceAllowedApiPath(apiPath: string): boolean {
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
