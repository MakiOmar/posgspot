/**
 * Runtime config for the Games Spot mobile storefront.
 */

function resolveApiBase(): string {
  const raw = (process.env.EXPO_PUBLIC_API_BASE || "").trim().replace(/\/$/, "");

  if (__DEV__) {
    return raw || "http://localhost:8000";
  }

  if (!raw) {
    throw new Error(
      "EXPO_PUBLIC_API_BASE is required in release builds (must be https).",
    );
  }
  if (!/^https:\/\//i.test(raw)) {
    throw new Error(
      `EXPO_PUBLIC_API_BASE must use https in release builds (got: ${raw}).`,
    );
  }
  return raw;
}

export const API_BASE: string = resolveApiBase();

export const STOREFRONT_WEB_URL: string = (
  process.env.EXPO_PUBLIC_STOREFRONT_URL || API_BASE
).replace(/\/$/, "");

/** External POS console tracking portal (same as Qwik). */
export const TRACK_CONSOLE_URL =
  process.env.EXPO_PUBLIC_TRACK_CONSOLE_URL ||
  "https://accounts.gamesspoteg.com/device/track";

export const CLIENT_HEADER = "mobile";
