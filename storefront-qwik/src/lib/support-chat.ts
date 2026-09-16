/** Client helpers for storefront AI support chat persistence + API. */

import { storefrontFetch, type FetchResult } from "~/lib/api";

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
