/** PlayStation platform on digital catalog routes (`4` = PS4, `5` = PS5). */
export type DigitalPlatform = "4" | "5";
export type DigitalOfferType = "primary" | "secondary";

function hasOwn(game: Record<string, unknown>, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(game, key);
}

function asNumber(value: unknown): number {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

/**
 * Stock for this platform + offer only. Missing / empty is 0 — never fall back
 * to the other platform or `total_*` aggregates (those caused false "in stock").
 */
export function digitalOfferStock(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
  type: DigitalOfferType,
): number {
  const key = `ps${platform}_${type}_stock`;
  if (!hasOwn(game, key) || game[key] === null || game[key] === "") {
    return 0;
  }
  return Math.max(0, asNumber(game[key]));
}

/** Offer enabled on this platform (Accounts status `1`), not a stock signal. */
export function digitalOfferEnabled(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
  type: DigitalOfferType,
): boolean {
  const key = `ps${platform}_${type}_status`;
  if (!hasOwn(game, key) || game[key] === null || game[key] === "") {
    return false;
  }
  const value = game[key];
  return value === true || value === 1 || value === "1";
}

/** Catalog unit price; generic keys are OK when the platform field is empty. */
export function digitalOfferPrice(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
  type: DigitalOfferType,
): number {
  const specific = asNumber(game[`ps${platform}_${type}_price`]);
  if (specific > 0) {
    return specific;
  }
  const generic = asNumber(game[type === "primary" ? "primary_price" : "secondary_price"]);
  return generic > 0 ? generic : 0;
}

export function digitalOfferInStock(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
  type: DigitalOfferType,
): boolean {
  return (
    digitalOfferEnabled(game, platform, type) &&
    digitalOfferPrice(game, platform, type) > 0 &&
    digitalOfferStock(game, platform, type) > 0
  );
}

/**
 * Live check-stock after HTTP 200. Do not treat a missing `stock` key as 0 —
 * Accounts often returns `{ is_available: true }` with no quantity.
 */
export function liveCheckStockIsOut(data: {
  is_available?: boolean | number | string;
  stock?: number | string | null;
} | null | undefined): boolean {
  if (!data) {
    return false;
  }
  if (data.is_available === false || data.is_available === 0 || data.is_available === "0") {
    return true;
  }
  if (data.stock === undefined || data.stock === null || data.stock === "") {
    return false;
  }
  const n = Number(data.stock);
  return Number.isFinite(n) && n <= 0;
}
