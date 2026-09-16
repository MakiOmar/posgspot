/** Client helpers for storefront AI support chat persistence + API. */

import { storefrontFetch, type FetchResult } from "~/lib/api";
import { localePath } from "~/lib/i18n/paths";

export const SUPPORT_GUEST_TOKEN_KEY = "gs-support-guest-v1";
export const SUPPORT_ACTIVE_UUID_KEY = "gs-support-active-v1";
export const SUPPORT_OPEN_EVENT = "gs-open-support-chat";

export interface SupportMessageDto {
  id: number;
  role: string;
  content: string;
  meta: Record<string, unknown> | null;
  created_at: string | null;
}

export interface SupportConversationDto {
  uuid: string;
  title: string | null;
  status: string;
  locale: string;
  escalation_id: number | null;
  last_message_at: string | null;
  messages: SupportMessageDto[];
  escalation_available?: boolean;
  preview?: string | null;
}

function authHeaders(token: string | null | undefined): Record<string, string> {
  const headers: Record<string, string> = {
    "X-Support-Guest-Token": ensureGuestToken(),
  };
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }
  return headers;
}

export function ensureGuestToken(): string {
  if (typeof localStorage === "undefined") {
    return crypto.randomUUID();
  }
  let token = localStorage.getItem(SUPPORT_GUEST_TOKEN_KEY);
  if (!token || !/^[0-9a-f-]{36}$/i.test(token)) {
    token = crypto.randomUUID();
    localStorage.setItem(SUPPORT_GUEST_TOKEN_KEY, token);
  }
  return token;
}

export function getActiveConversationUuid(): string | null {
  if (typeof localStorage === "undefined") return null;
  return localStorage.getItem(SUPPORT_ACTIVE_UUID_KEY);
}

export function setActiveConversationUuid(uuid: string | null): void {
  if (typeof localStorage === "undefined") return;
  if (uuid) {
    localStorage.setItem(SUPPORT_ACTIVE_UUID_KEY, uuid);
  } else {
    localStorage.removeItem(SUPPORT_ACTIVE_UUID_KEY);
  }
}

export function openSupportChatEvent(): void {
  if (typeof window === "undefined") return;
  window.dispatchEvent(new CustomEvent(SUPPORT_OPEN_EVENT));
}

export function supportGuestTokenHeader(): Record<string, string> {
  if (typeof localStorage === "undefined") return {};
  const token = localStorage.getItem(SUPPORT_GUEST_TOKEN_KEY);
  return token ? { "X-Support-Guest-Token": token } : {};
}

export function listSupportConversations(
  token: string | null,
  locale: string,
  page = 1,
): Promise<FetchResult<{ conversations: SupportConversationDto[] }>> {
  return storefrontFetch<{ conversations: SupportConversationDto[] }>(
    `/support/conversations?page=${page}`,
    { headers: authHeaders(token) },
    locale,
  );
}

export function createSupportConversation(
  token: string | null,
  locale: string,
): Promise<FetchResult<SupportConversationDto>> {
  return storefrontFetch<SupportConversationDto>(
    "/support/conversations",
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ locale }),
    },
    locale,
  );
}

export function getSupportConversation(
  uuid: string,
  token: string | null,
  locale: string,
): Promise<FetchResult<SupportConversationDto>> {
  return storefrontFetch<SupportConversationDto>(
    `/support/conversations/${uuid}`,
    { headers: authHeaders(token) },
    locale,
  );
}

export function sendSupportMessage(
  uuid: string,
  token: string | null,
  locale: string,
  message: string,
  pagePath?: string,
): Promise<FetchResult<SupportConversationDto>> {
  return storefrontFetch<SupportConversationDto>(
    `/support/conversations/${uuid}/messages`,
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({
        message,
        locale,
        page_context: pagePath ? { path: pagePath } : undefined,
      }),
    },
    locale,
  );
}

export function escalateSupportConversation(
  uuid: string,
  token: string,
  locale: string,
  note?: string,
): Promise<FetchResult<{ reference_no: string; conversation: SupportConversationDto; message: string }>> {
  return storefrontFetch(
    `/support/conversations/${uuid}/escalate`,
    {
      method: "POST",
      headers: authHeaders(token),
      body: JSON.stringify({ note: note || undefined }),
    },
    locale,
  );
}

export function claimSupportConversations(
  token: string,
  locale: string,
): Promise<FetchResult<{ claimed: number }>> {
  return storefrontFetch(
    "/support/conversations/claim",
    {
      method: "POST",
      headers: authHeaders(token),
    },
    locale,
  );
}

export function whatsappHref(raw: string): string {
  const digits = raw.replace(/\D+/g, "");
  return digits ? `https://wa.me/${digits}` : "#";
}

function escapeHtml(raw: string): string {
  return raw
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function escapeAttr(raw: string): string {
  return escapeHtml(raw).replace(/'/g, "&#39;");
}

/** Resolve markdown / bare hrefs; locale-prefix storefront-relative paths. */
function resolveSupportHref(href: string, locale: string): string | null {
  const trimmed = href.trim();
  if (!trimmed || /^javascript:/i.test(trimmed)) {
    return null;
  }
  if (/^(https?:|mailto:|tel:)/i.test(trimmed)) {
    return trimmed;
  }
  if (trimmed.startsWith("/")) {
    return localePath(locale, trimmed);
  }
  // Models sometimes omit the leading slash (e.g. contact or contact/).
  if (/^[a-z][\w/-]*$/i.test(trimmed) && !trimmed.includes("://")) {
    return localePath(locale, `/${trimmed}`);
  }
  return null;
}

function isExternalHref(href: string): boolean {
  return /^(https?:|mailto:)/i.test(href);
}

function isPhoneCandidate(digits: string, hotlineDigits: string): boolean {
  if (!digits) return false;
  if (hotlineDigits && digits === hotlineDigits) return true;
  // Egyptian mobiles
  if (/^01[0125]\d{8}$/.test(digits)) return true;
  // International E.164-ish
  if (/^\d{8,15}$/.test(digits) && digits.startsWith("20")) return true;
  if (/^\d{10,15}$/.test(digits)) return true;
  // Local service / hotline numbers (e.g. 17797)
  if (/^1\d{3,5}$/.test(digits)) return true;
  return false;
}

/**
 * Turn assistant/user chat text into safe HTML: markdown links, bare URLs, and tel: phones.
 * Output must be passed through DOMPurify before inserting into the DOM.
 */
export function formatSupportMessageHtml(
  raw: string,
  opts?: { locale?: string; hotline?: string },
): string {
  if (!raw) return "";

  const locale = opts?.locale || "en";
  const hotlineDigits = (opts?.hotline || "17797").replace(/\D+/g, "");
  let text = escapeHtml(raw);

  const slots: string[] = [];
  const park = (html: string): string => {
    const i = slots.length;
    slots.push(html);
    return `\u0000${i}\u0000`;
  };

  // Markdown links: [label](href)
  text = text.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (_m, label: string, href: string) => {
    const safeHref = resolveSupportHref(href, locale);
    if (!safeHref) return label;
    const attrs = isExternalHref(safeHref)
      ? ` target="_blank" rel="noopener noreferrer"`
      : "";
    return park(`<a href="${escapeAttr(safeHref)}"${attrs}>${label}</a>`);
  });

  // Bare http(s) URLs
  text = text.replace(/https?:\/\/[^\s<]+/gi, (url) => {
    const cleaned = url.replace(/[.,;:!?)]+$/, "");
    const trail = url.slice(cleaned.length);
    return (
      park(
        `<a href="${escapeAttr(cleaned)}" target="_blank" rel="noopener noreferrer">${cleaned}</a>`,
      ) + trail
    );
  });

  // Phone numbers (after links so we do not touch digits inside hrefs)
  text = text.replace(
    /(?:\+?\d[\d\s\-()]{3,18}\d|\b1\d{3,5}\b|\b01[0125]\d{8}\b)/g,
    (match) => {
      const digits = match.replace(/\D+/g, "");
      if (!isPhoneCandidate(digits, hotlineDigits)) return match;
      return park(`<a href="tel:${escapeAttr(digits)}">${match}</a>`);
    },
  );

  return text.replace(/\u0000(\d+)\u0000/g, (_m, i: string) => slots[Number(i)] || "");
}

/** Human-friendly timestamp for history list (EN / Egyptian Arabic). */
export function formatSupportDateTime(
  iso: string | null | undefined,
  locale: string,
): string {
  if (!iso) return "";
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return iso;
  return new Intl.DateTimeFormat(locale === "ar" ? "ar-EG" : "en-GB", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(date);
}
