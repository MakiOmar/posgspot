import type { StoreLocation } from "~/lib/types";

/** Google Maps embed URL for a single store (coords preferred, then address). */
export function storeMapEmbedUrl(location: StoreLocation | null | undefined, fallbackQuery = "Cairo, Egypt"): string {
  if (location?.latitude != null && location.longitude != null) {
    return `https://maps.google.com/maps?q=${location.latitude},${location.longitude}&z=15&output=embed`;
  }
  if (location?.address) {
    return `https://maps.google.com/maps?q=${encodeURIComponent(location.address)}&z=15&output=embed`;
  }
  return `https://maps.google.com/maps?q=${encodeURIComponent(fallbackQuery)}&z=11&output=embed`;
}

/** Prefer first location with coordinates, else the first branch. */
export function pickDefaultStore(locations: StoreLocation[]): StoreLocation | null {
  if (locations.length === 0) {
    return null;
  }
  return locations.find((loc) => loc.latitude != null && loc.longitude != null) ?? locations[0];
}
