/**
 * Runtime config for the Games Spot mobile storefront.
 */
export const API_BASE: string =
  (process.env.EXPO_PUBLIC_API_BASE || "http://localhost:8000").replace(
    /\/$/,
    "",
  );

export const STOREFRONT_WEB_URL: string = (
  process.env.EXPO_PUBLIC_STOREFRONT_URL || API_BASE
).replace(/\/$/, "");

export const CLIENT_HEADER = "mobile";
