/** PlayStation platform on digital catalog routes (`4` = PS4, `5` = PS5). */
export type DigitalPlatform = "4" | "5";
export type DigitalOfferType = "primary" | "secondary" | "full";
export type DigitalProductType = "game" | "subscription";

export const DIGITAL_OFFER_TYPES: DigitalOfferType[] = ["primary", "secondary", "full"];

export interface DigitalReviewItem {
  id: number;
  stars: number;
  comment: string;
  reviewer_name: string;
  avatar_url?: string | null;
  created_at: string | null;
}

export interface DigitalReviewsPayload {
  average: number;
  count: number;
  items: DigitalReviewItem[];
}

function hasOwn(game: Record<string, unknown>, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(game, key);
}

function asNumber(value: unknown): number {
  const n = Number(value);
  return Number.isFinite(n) ? n : 0;
}

/** Cover + Accounts gallery URLs (deduped) for the digital PDP. */
export function digitalGalleryUrls(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
): string[] {
  const cover =
    platform === "5"
      ? String(game.ps5_image_url ?? game.image_url ?? "")
      : String(game.ps4_image_url ?? game.image_url ?? "");
  const out: string[] = [];
  const seen = new Set<string>();
  const push = (src: string) => {
    const url = src.trim();
    if (!url || seen.has(url)) {
      return;
    }
    seen.add(url);
    out.push(url);
  };
  push(cover);
  const gallery = Array.isArray(game.gallery) ? game.gallery : [];
  for (const row of gallery) {
    if (typeof row === "string") {
      push(row);
      continue;
    }
    if (row && typeof row === "object" && "url" in row) {
      push(String((row as { url?: unknown }).url ?? ""));
    }
  }
  return out;
}

/** Approved reviews summary from Accounts (via storefront proxy). */
export function digitalReviewsFromGame(game: Record<string, unknown>): DigitalReviewsPayload {
  const raw = game.reviews;
  if (!raw || typeof raw !== "object") {
    return { average: 0, count: 0, items: [] };
  }
  const reviews = raw as Record<string, unknown>;
  const itemsRaw = Array.isArray(reviews.items) ? reviews.items : [];
  const items: DigitalReviewItem[] = [];
  for (const row of itemsRaw) {
    if (!row || typeof row !== "object") {
      continue;
    }
    const item = row as Record<string, unknown>;
    items.push({
      id: Number(item.id) || 0,
      stars: Math.max(1, Math.min(5, Number(item.stars) || 0)),
      comment: String(item.comment ?? ""),
      reviewer_name: String(item.reviewer_name ?? "Customer"),
      avatar_url:
        typeof item.avatar_url === "string" && item.avatar_url.trim()
          ? item.avatar_url.trim()
          : null,
      created_at: item.created_at != null ? String(item.created_at) : null,
    });
  }
  return {
    average: Math.max(0, asNumber(reviews.average)),
    count: Math.max(0, Math.floor(asNumber(reviews.count) || items.length)),
    items,
  };
}

/** First sellable offer (price + enabled), preferring Primary → Secondary → Full. */
export function pickDefaultDigitalOffer(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
): DigitalOfferType {
  for (const type of DIGITAL_OFFER_TYPES) {
    if (digitalOfferEnabled(game, platform, type) && digitalOfferPrice(game, platform, type) > 0) {
      return type;
    }
  }
  return "primary";
}

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

export function digitalOfferEnabled(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
  type: DigitalOfferType,
): boolean {
  const key = `ps${platform}_${type}_status`;
  if (hasOwn(game, key) && game[key] !== null && game[key] !== "") {
    const value = game[key];
    return value === true || value === 1 || value === "1";
  }
  if (type === "full") {
    return digitalOfferPrice(game, platform, "full") > 0 || digitalOfferStock(game, platform, "full") > 0;
  }
  return false;
}

export function digitalOfferPrice(
  game: Record<string, unknown>,
  platform: DigitalPlatform,
  type: DigitalOfferType,
): number {
  const specific = asNumber(game[`ps${platform}_${type}_price`]);
  if (specific > 0) {
    return specific;
  }
  const genericKey =
    type === "secondary" ? "secondary_price" : type === "full" ? "full_price" : "primary_price";
  const generic = asNumber(game[genericKey]);
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
