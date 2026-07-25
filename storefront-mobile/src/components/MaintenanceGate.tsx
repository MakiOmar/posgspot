import { Redirect, usePathname } from "expo-router";
import type { ReactNode } from "react";
import { useApp } from "../contexts/AppContext";

function isMaintenancePath(pathname: string | null | undefined): boolean {
  if (!pathname) return false;
  return (
    pathname === "/maintenance" ||
    pathname.endsWith("/maintenance") ||
    pathname.includes("/maintenance/")
  );
}

/**
 * Global storefront maintenance gate (Qwik parity).
 * Blocks all shop routes while settings.maintenance_mode is on.
 */
export function MaintenanceGate({ children }: { children: ReactNode }) {
  const { settings, loading } = useApp();
  const pathname = usePathname();

  if (loading || !settings) {
    return <>{children}</>;
  }

  const onMaintenance = isMaintenancePath(pathname);

  if (settings.maintenance_mode && !onMaintenance) {
    return <Redirect href="/maintenance" />;
  }

  if (!settings.maintenance_mode && onMaintenance) {
    return <Redirect href="/(tabs)" />;
  }

  return <>{children}</>;
}
