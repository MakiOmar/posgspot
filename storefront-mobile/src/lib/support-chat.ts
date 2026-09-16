import * as SecureStore from "expo-secure-store";
import { authHeaders, storefrontFetch, type FetchResult } from "./api";
import type { ContentLocale } from "./types";

const GUEST_KEY = "gs-support-guest-v1";
const ACTIVE_KEY = "gs-support-active-v1";

const SECURE_OPTS: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.AFTER_FIRST_UNLOCK_THIS_DEVICE_ONLY,
};

export type SupportMessageDto = {
  id: number;
  role: string;
  content: string;
  meta: Record<string, unknown> | null;
  created_at: string | null;
};

export type SupportConversationDto = {
  uuid: string;
  title: string | null;
  status: string;
  locale: string;
  escalation_id: number | null;
  last_message_at: string | null;
  messages?: SupportMessageDto[];
  escalation_available?: boolean;
  preview?: string | null;
};

function randomUuid(): string {
  return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === "x" ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

export async function ensureGuestToken(): Promise<string> {
  try {
    const existing = await SecureStore.getItemAsync(GUEST_KEY, SECURE_OPTS);
    if (existing && /^[0-9a-f-]{36}$/i.test(existing)) {
      return existing;
    }
  } catch {
    // fall through
  }
  const token = randomUuid();
  try {
    await SecureStore.setItemAsync(GUEST_KEY, token, SECURE_OPTS);
  } catch {
    // ignore
  }
  return token;
}

export async function getActiveConversationUuid(): Promise<string | null> {
  try {
    return await SecureStore.getItemAsync(ACTIVE_KEY, SECURE_OPTS);
  } catch {
    return null;
  }
}

export async function setActiveConversationUuid(uuid: string | null): Promise<void> {
  try {
    if (uuid) {
      await SecureStore.setItemAsync(ACTIVE_KEY, uuid, SECURE_OPTS);
    } else {
      await SecureStore.deleteItemAsync(ACTIVE_KEY, SECURE_OPTS);
    }
  } catch {
    // ignore
  }
}

async function supportHeaders(token: string | null | undefined): Promise<Record<string, string>> {
  const guest = await ensureGuestToken();
  return {
    ...authHeaders(token),
    "X-Support-Guest-Token": guest,
  };
}

export async function listSupportConversations(
  token: string | null,
  locale: ContentLocale,
  page = 1,
): Promise<FetchResult<{ conversations: SupportConversationDto[] }>> {
  return storefrontFetch(
    `/support/conversations?page=${page}`,
    { headers: await supportHeaders(token) },
    locale,
  );
}

export async function createSupportConversation(
  token: string | null,
  locale: ContentLocale,
): Promise<FetchResult<SupportConversationDto>> {
  return storefrontFetch(
    "/support/conversations",
    {
      method: "POST",
      headers: await supportHeaders(token),
      body: JSON.stringify({ locale }),
    },
    locale,
  );
}

export async function getSupportConversation(
  uuid: string,
  token: string | null,
  locale: ContentLocale,
): Promise<FetchResult<SupportConversationDto>> {
  return storefrontFetch(
    `/support/conversations/${uuid}`,
    { headers: await supportHeaders(token) },
    locale,
  );
}

export async function sendSupportMessage(
  uuid: string,
  token: string | null,
  locale: ContentLocale,
  message: string,
): Promise<FetchResult<SupportConversationDto>> {
  return storefrontFetch(
    `/support/conversations/${uuid}/messages`,
    {
      method: "POST",
      headers: await supportHeaders(token),
      body: JSON.stringify({ message, locale }),
    },
    locale,
  );
}

export async function escalateSupportConversation(
  uuid: string,
  token: string,
  locale: ContentLocale,
  note?: string,
): Promise<
  FetchResult<{ reference_no: string; conversation: SupportConversationDto; message: string }>
> {
  return storefrontFetch(
    `/support/conversations/${uuid}/escalate`,
    {
      method: "POST",
      headers: await supportHeaders(token),
      body: JSON.stringify({ note: note || undefined }),
    },
    locale,
  );
}

export async function guestTokenHeader(): Promise<Record<string, string>> {
  try {
    const guest = await SecureStore.getItemAsync(GUEST_KEY, SECURE_OPTS);
    return guest ? { "X-Support-Guest-Token": guest } : {};
  } catch {
    return {};
  }
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
