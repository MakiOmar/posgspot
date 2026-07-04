import { stripLocalePrefix } from "~/lib/i18n/paths";

/** Public routes that stay reachable while storefront maintenance mode is on. */
const MAINTENANCE_EXEMPT_BARE_PATHS = ["/add-customer"] as const;

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
