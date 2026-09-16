import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation } from "@builder.io/qwik-city";
import { useAuth } from "~/lib/auth-context";
import { ApiError } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { useSiteShell } from "~/lib/site-shell-context";
import {
  SUPPORT_OPEN_EVENT,
  createSupportConversation,
  escalateSupportConversation,
  formatSupportDateTime,
  getActiveConversationUuid,
  getSupportConversation,
  listSupportConversations,
  sendSupportMessage,
  setActiveConversationUuid,
  type SupportConversationDto,
  whatsappHref,
} from "~/lib/support-chat";

type TabId = "current" | "history";

/** Chat bubble SVG icon for the floating launcher. */
const ChatIcon = () => (
  <svg class="support-chat__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path
      fill="currentColor"
      d="M4.5 4.75h15a1.75 1.75 0 0 1 1.75 1.75v8.5A1.75 1.75 0 0 1 19.5 16.75H13.1l-3.55 3.2a.75.75 0 0 1-1.25-.55v-2.65H4.5A1.75 1.75 0 0 1 2.75 15V6.5A1.75 1.75 0 0 1 4.5 4.75Zm1.25 3.5a.75.75 0 0 0 0 1.5h11.5a.75.75 0 0 0 0-1.5H5.75Zm0 3.25a.75.75 0 0 0 0 1.5h7.5a.75.75 0 0 0 0-1.5h-7.5Z"
    />
  </svg>
);

/**
 * Floating AI support chat. Lazy-mounted from the lang layout when enabled.
 * Visual language matches the dark Games Spot storefront shell.
 */
export const SupportChatWidget = component$(() => {
  const { locale } = useI18n();
  const auth = useAuth();
  const shell = useSiteShell();
  const loc = useLocation();
  const open = useSignal(false);
  const tab = useSignal<TabId>("current");
  const loading = useSignal(false);
  const sending = useSignal(false);
  const error = useSignal("");
  const draft = useSignal("");
  const conversation = useSignal<SupportConversationDto | null>(null);
  const history = useSignal<SupportConversationDto[]>([]);

  const settings = shell.settings;
  const enabled = Boolean(settings.support_chat?.enabled);
  const phone = settings.contact?.phone?.trim() || "17797";
  const whatsapp = settings.contact?.whatsapp?.trim() || "";
  const brand = settings.business_name?.trim() || "Games Spot";

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    const onOpen = () => {
      open.value = true;
    };
    window.addEventListener(SUPPORT_OPEN_EVENT, onOpen);
    if (loc.url.searchParams.get("chat") === "1") {
      open.value = true;
    }
    cleanup(() => window.removeEventListener(SUPPORT_OPEN_EVENT, onOpen));
  });

  const loadCurrent$ = $(async () => {
    if (!enabled) return;
    loading.value = true;
    error.value = "";
    try {
      const token = auth.token;
      const uuid = getActiveConversationUuid();
      if (uuid) {
        try {
          const { data } = await getSupportConversation(uuid, token, locale);
          conversation.value = data;
          setActiveConversationUuid(data.uuid);
          return;
        } catch (e) {
          if (!(e instanceof ApiError && e.status === 404)) {
            throw e;
          }
          setActiveConversationUuid(null);
        }
      }
      const listed = await listSupportConversations(token, locale, 1);
      const first = listed.data.conversations[0];
      if (first) {
        const { data } = await getSupportConversation(first.uuid, token, locale);
        conversation.value = data;
        setActiveConversationUuid(data.uuid);
      } else {
        conversation.value = null;
      }
    } catch (e) {
      error.value =
        e instanceof ApiError ? e.message : tStatic(locale, "support.errorGeneric");
    } finally {
      loading.value = false;
    }
  });

  const loadHistory$ = $(async () => {
    loading.value = true;
    error.value = "";
    try {
      const { data } = await listSupportConversations(auth.token, locale, 1);
      history.value = data.conversations;
    } catch (e) {
      error.value =
        e instanceof ApiError ? e.message : tStatic(locale, "support.errorGeneric");
    } finally {
      loading.value = false;
    }
  });

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => open.value);
    track(() => tab.value);
    track(() => auth.token);
    if (!open.value || !enabled) return;
    if (tab.value === "current") {
      void loadCurrent$();
    } else {
      void loadHistory$();
    }
  });

  const ensureConversation$ = $(async () => {
    if (conversation.value?.uuid && conversation.value.status === "active") {
      return conversation.value.uuid;
    }
    const { data } = await createSupportConversation(auth.token, locale);
    conversation.value = data;
    setActiveConversationUuid(data.uuid);
    return data.uuid;
  });

  const send$ = $(async () => {
    const text = draft.value.trim();
    if (!text || sending.value) return;
    sending.value = true;
    error.value = "";
    try {
      const uuid = await ensureConversation$();
      const { data } = await sendSupportMessage(
        uuid,
        auth.token,
        locale,
        text,
        loc.url.pathname,
      );
      conversation.value = data;
      setActiveConversationUuid(data.uuid);
      draft.value = "";
      tab.value = "current";
    } catch (e) {
      error.value =
        e instanceof ApiError ? e.message : tStatic(locale, "support.errorGeneric");
    } finally {
      sending.value = false;
    }
  });

  const newChat$ = $(async () => {
    loading.value = true;
    error.value = "";
    try {
      const { data } = await createSupportConversation(auth.token, locale);
      conversation.value = data;
      setActiveConversationUuid(data.uuid);
      tab.value = "current";
    } catch (e) {
      error.value =
        e instanceof ApiError ? e.message : tStatic(locale, "support.errorGeneric");
    } finally {
      loading.value = false;
    }
  });

  const openHistoryItem$ = $(async (uuid: string) => {
    loading.value = true;
    error.value = "";
    try {
      const { data } = await getSupportConversation(uuid, auth.token, locale);
      conversation.value = data;
      setActiveConversationUuid(data.uuid);
      tab.value = "current";
    } catch (e) {
      error.value =
        e instanceof ApiError ? e.message : tStatic(locale, "support.errorGeneric");
    } finally {
      loading.value = false;
    }
  });

  const escalate$ = $(async () => {
    if (!auth.token || !conversation.value?.uuid) return;
    sending.value = true;
    error.value = "";
    try {
      const { data } = await escalateSupportConversation(
        conversation.value.uuid,
        auth.token,
        locale,
      );
      conversation.value = data.conversation;
      error.value = `${data.message} (${data.reference_no})`;
    } catch (e) {
      error.value =
        e instanceof ApiError ? e.message : tStatic(locale, "support.errorGeneric");
    } finally {
      sending.value = false;
    }
  });

  if (!enabled) {
    return null;
  }

  return (
    <div
      class={`support-chat${open.value ? " support-chat--open" : ""}`}
      dir={locale === "ar" ? "rtl" : "ltr"}
    >
      {/* Floating launcher */}
      <button
        type="button"
        class="support-chat__launcher"
        aria-expanded={open.value}
        aria-label={tStatic(locale, "support.open")}
        onClick$={() => {
          open.value = !open.value;
        }}
      >
        {open.value ? (
          <span class="support-chat__launcher-x" aria-hidden="true">
            ×
          </span>
        ) : (
          <ChatIcon />
        )}
      </button>

      {open.value ? (
        <section class="support-chat__panel" aria-label={tStatic(locale, "support.title")}>
          {/* Brand header */}
          <header class="support-chat__header">
            <div class="support-chat__brand">
              <span class="support-chat__avatar" aria-hidden="true">
                GS
              </span>
              <div class="support-chat__brand-text">
                <strong class="support-chat__title">{tStatic(locale, "support.title")}</strong>
                <p class="support-chat__subtitle">
                  <span class="support-chat__online" aria-hidden="true" />
                  {tStatic(locale, "support.subtitle")}
                </p>
              </div>
            </div>
            <button
              type="button"
              class="support-chat__icon-btn"
              aria-label={tStatic(locale, "support.close")}
              onClick$={() => {
                open.value = false;
              }}
            >
              ×
            </button>
          </header>

          <div class="support-chat__tabs" role="tablist">
            <button
              type="button"
              role="tab"
              class={tab.value === "current" ? "is-active" : undefined}
              aria-selected={tab.value === "current"}
              onClick$={() => {
                tab.value = "current";
              }}
            >
              {tStatic(locale, "support.tabCurrent")}
            </button>
            <button
              type="button"
              role="tab"
              class={tab.value === "history" ? "is-active" : undefined}
              aria-selected={tab.value === "history"}
              onClick$={() => {
                tab.value = "history";
              }}
            >
              {tStatic(locale, "support.tabHistory")}
            </button>
          </div>

          {error.value ? <p class="support-chat__error">{error.value}</p> : null}

          {tab.value === "history" ? (
            <div class="support-chat__history">
              <button
                type="button"
                class="btn btn-primary support-chat__new"
                onClick$={newChat$}
              >
                {tStatic(locale, "support.newChat")}
              </button>
              {loading.value ? (
                <p class="support-chat__muted">{tStatic(locale, "support.loading")}</p>
              ) : history.value.length === 0 ? (
                <p class="support-chat__muted">{tStatic(locale, "support.emptyHistory")}</p>
              ) : (
                <ul>
                  {history.value.map((item) => (
                    <li key={item.uuid}>
                      <button type="button" onClick$={() => openHistoryItem$(item.uuid)}>
                        <span class="support-chat__history-title">
                          {item.title || tStatic(locale, "support.untitled")}
                        </span>
                        <small>
                          {item.last_message_at
                            ? formatSupportDateTime(item.last_message_at, locale)
                            : item.status}
                        </small>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          ) : (
            <>
              <div class="support-chat__messages" aria-live="polite">
                {loading.value && !conversation.value ? (
                  <p class="support-chat__muted">{tStatic(locale, "support.loading")}</p>
                ) : null}
                {!loading.value &&
                (!conversation.value || conversation.value.messages.length === 0) ? (
                  <div class="support-chat__welcome">
                    <p class="support-chat__welcome-brand">{brand}</p>
                    <p class="support-chat__muted">{tStatic(locale, "support.emptyCurrent")}</p>
                  </div>
                ) : null}
                {(conversation.value?.messages || [])
                  .filter((m) => m.role === "user" || m.role === "assistant")
                  .map((m) => (
                    <div
                      key={m.id}
                      class={`support-chat__bubble support-chat__bubble--${m.role}`}
                    >
                      {m.content}
                    </div>
                  ))}
              </div>

              <form
                class="support-chat__composer"
                preventdefault:submit
                onSubmit$={send$}
              >
                <label class="sr-only" for="support-chat-input">
                  {tStatic(locale, "support.messageLabel")}
                </label>
                <div class="support-chat__composer-row">
                  <textarea
                    id="support-chat-input"
                    rows={2}
                    value={draft.value}
                    disabled={sending.value}
                    placeholder={tStatic(locale, "support.placeholder")}
                    onInput$={(e) => {
                      draft.value = (e.target as HTMLTextAreaElement).value;
                    }}
                    onKeyDown$={(e) => {
                      // Enter sends; Shift+Enter keeps a newline
                      if (e.key === "Enter" && !e.shiftKey) {
                        e.preventDefault();
                        void send$();
                      }
                    }}
                  />
                  <button
                    type="submit"
                    class="support-chat__send"
                    disabled={sending.value}
                  >
                    {sending.value
                      ? tStatic(locale, "support.sending")
                      : tStatic(locale, "support.send")}
                  </button>
                </div>
              </form>
            </>
          )}

          <footer class="support-chat__handoff">
            <a class="support-chat__chip" href={`tel:${phone}`}>
              {tStatic(locale, "support.call")}
            </a>
            {whatsapp ? (
              <a
                class="support-chat__chip"
                href={whatsappHref(whatsapp)}
                target="_blank"
                rel="noopener noreferrer"
              >
                {tStatic(locale, "support.whatsapp")}
              </a>
            ) : null}
            <a class="support-chat__chip" href={localePath(locale, "/contact")}>
              {tStatic(locale, "support.contactForm")}
            </a>
            {auth.token && conversation.value?.escalation_available ? (
              <button type="button" class="support-chat__chip" onClick$={escalate$}>
                {tStatic(locale, "support.escalate")}
              </button>
            ) : null}
            <button type="button" class="support-chat__chip" onClick$={newChat$}>
              {tStatic(locale, "support.newChat")}
            </button>
          </footer>
        </section>
      ) : null}
    </div>
  );
});
