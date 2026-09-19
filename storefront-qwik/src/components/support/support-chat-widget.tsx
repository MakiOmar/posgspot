import { $, component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation } from "@builder.io/qwik-city";
import { useAuth } from "~/lib/auth-context";
import { ApiError } from "~/lib/api";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { useSiteShell } from "~/lib/site-shell-context";
import { SanitizedHtml } from "~/components/ui/sanitized-html";
import {
  SUPPORT_OPEN_EVENT,
  createSupportConversation,
  escalateSupportConversation,
  formatSupportDateTime,
  formatSupportMessageHtml,
  getActiveConversationUuid,
  getSupportConversation,
  listSupportConversations,
  sendSupportMessage,
  setActiveConversationUuid,
  type SupportConversationDto,
  whatsappHref,
} from "~/lib/support-chat";

type TabId = "current" | "history";

/** Contact / headset icon for the floating launcher. */
const ContactIcon = () => (
  <svg class="support-chat__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path
      fill="currentColor"
      d="M12 1.75a6.25 6.25 0 0 0-6.25 6.25v2.1A3.75 3.75 0 0 0 3.5 13.75v1.5A2.75 2.75 0 0 0 6.25 18h1.1a1.6 1.6 0 0 0 1.6-1.6v-2.3a1.6 1.6 0 0 0-1.6-1.6H6.4V8A5.1 5.1 0 0 1 12 2.9 5.1 5.1 0 0 1 17.6 8v4.5h-.95a1.6 1.6 0 0 0-1.6 1.6v2.3a1.6 1.6 0 0 0 1.6 1.6h1.1A2.75 2.75 0 0 0 20.5 15.25v-1.5a3.75 3.75 0 0 0-2.25-3.4V8A6.25 6.25 0 0 0 12 1.75Zm0 18a3.1 3.1 0 0 0 2.85-1.9h-5.7A3.1 3.1 0 0 0 12 19.75Z"
    />
  </svg>
);

const PhoneMiniIcon = () => (
  <svg class="support-chat__menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path
      fill="currentColor"
      d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"
    />
  </svg>
);

const ChatMiniIcon = () => (
  <svg class="support-chat__menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path
      fill="currentColor"
      d="M4.5 4.75h15a1.75 1.75 0 0 1 1.75 1.75v8.5A1.75 1.75 0 0 1 19.5 16.75H13.1l-3.55 3.2a.75.75 0 0 1-1.25-.55v-2.65H4.5A1.75 1.75 0 0 1 2.75 15V6.5A1.75 1.75 0 0 1 4.5 4.75Z"
    />
  </svg>
);

const MessageMiniIcon = () => (
  <svg class="support-chat__menu-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path
      fill="currentColor"
      d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 2v.5l8 5 8-5V6H4Zm0 3.2V18h16V9.2l-7.45 4.66a1.25 1.25 0 0 1-1.1 0L4 9.2Z"
    />
  </svg>
);

/**
 * Floating contact launcher with Call / Chat / Message options.
 * Chat opens the AI support panel when the feature is enabled.
 */
export const SupportChatWidget = component$(() => {
  const { locale } = useI18n();
  const auth = useAuth();
  const shell = useSiteShell();
  const loc = useLocation();
  const menuOpen = useSignal(false);
  const chatOpen = useSignal(false);
  const tab = useSignal<TabId>("current");
  const loading = useSignal(false);
  const sending = useSignal(false);
  const error = useSignal("");
  const draft = useSignal("");
  const conversation = useSignal<SupportConversationDto | null>(null);
  const history = useSignal<SupportConversationDto[]>([]);

  const settings = shell.settings;
  const chatEnabled = Boolean(settings.support_chat?.enabled);
  const phone = settings.contact?.phone?.trim() || "17797";
  const phoneHref = `tel:${phone.replace(/[^\d+]/g, "") || "17797"}`;
  const whatsapp = settings.contact?.whatsapp?.trim() || "";
  const brand = settings.business_name?.trim() || "Games Spot";

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ cleanup }) => {
    const onOpen = () => {
      if (!chatEnabled) return;
      menuOpen.value = false;
      chatOpen.value = true;
    };
    window.addEventListener(SUPPORT_OPEN_EVENT, onOpen);
    if (chatEnabled && loc.url.searchParams.get("chat") === "1") {
      chatOpen.value = true;
    }
    cleanup(() => window.removeEventListener(SUPPORT_OPEN_EVENT, onOpen));
  });

  const loadCurrent$ = $(async () => {
    if (!chatEnabled) return;
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
    track(() => chatOpen.value);
    track(() => tab.value);
    track(() => auth.token);
    if (!chatOpen.value || !chatEnabled) return;
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

  return (
    <div
      class={`support-chat${menuOpen.value ? " support-chat--menu-open" : ""}${
        chatOpen.value ? " support-chat--open" : ""
      }`}
      dir={locale === "ar" ? "rtl" : "ltr"}
    >
      {menuOpen.value ? (
        <div class="support-chat__menu" role="menu" aria-label={tStatic(locale, "support.menuAria")}>
          <a class="support-chat__menu-item" href={phoneHref} role="menuitem" dir="ltr">
            <PhoneMiniIcon />
            <span>{tStatic(locale, "support.callUs")}</span>
          </a>
          {chatEnabled ? (
            <button
              type="button"
              class="support-chat__menu-item"
              role="menuitem"
              onClick$={() => {
                menuOpen.value = false;
                chatOpen.value = true;
              }}
            >
              <ChatMiniIcon />
              <span>{tStatic(locale, "support.chatWithAgent")}</span>
            </button>
          ) : (
            <span class="support-chat__menu-item support-chat__menu-item--disabled" role="menuitem">
              <ChatMiniIcon />
              <span>{tStatic(locale, "support.chatUnavailable")}</span>
            </span>
          )}
          <a
            class="support-chat__menu-item"
            href={localePath(locale, "/contact")}
            role="menuitem"
          >
            <MessageMiniIcon />
            <span>{tStatic(locale, "support.leaveMessage")}</span>
          </a>
        </div>
      ) : null}

      {/* Floating contact launcher */}
      <button
        type="button"
        class="support-chat__launcher"
        aria-expanded={menuOpen.value || chatOpen.value}
        aria-label={
          menuOpen.value || chatOpen.value
            ? tStatic(locale, "support.close")
            : tStatic(locale, "support.open")
        }
        onClick$={() => {
          if (chatOpen.value) {
            chatOpen.value = false;
            return;
          }
          menuOpen.value = !menuOpen.value;
        }}
      >
        {menuOpen.value || chatOpen.value ? (
          <span class="support-chat__launcher-x" aria-hidden="true">
            ×
          </span>
        ) : (
          <ContactIcon />
        )}
      </button>

      {chatOpen.value && chatEnabled ? (
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
              aria-label={tStatic(locale, "support.closeChat")}
              onClick$={() => {
                chatOpen.value = false;
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
                    <SanitizedHtml
                      key={m.id}
                      class={`support-chat__bubble support-chat__bubble--${m.role}`}
                      html={formatSupportMessageHtml(m.content, {
                        locale,
                        hotline: phone,
                      })}
                    />
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
