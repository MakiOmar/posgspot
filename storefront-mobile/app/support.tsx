import { useCallback, useEffect, useLayoutEffect, useState } from "react";
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { useNavigation } from "expo-router";
import { ApiError } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../src/components/ui";
import {
  createSupportConversation,
  escalateSupportConversation,
  formatSupportDateTime,
  getActiveConversationUuid,
  getSupportConversation,
  listSupportConversations,
  sendSupportMessage,
  setActiveConversationUuid,
  type SupportConversationDto,
} from "../src/lib/support-chat";

type TabId = "current" | "history";

export default function SupportScreen() {
  const navigation = useNavigation();
  const { t, locale, token, settings, accent } = useApp();
  const [tab, setTab] = useState<TabId>("current");
  const [loading, setLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [draft, setDraft] = useState("");
  const [conversation, setConversation] = useState<SupportConversationDto | null>(null);
  const [history, setHistory] = useState<SupportConversationDto[]>([]);

  // Allow chat while settings are still loading; only block when explicitly off.
  const enabled =
    settings == null ||
    Boolean(
      (settings as { support_chat?: { enabled?: boolean } } | null)?.support_chat
        ?.enabled,
    );
  const phone = String(
    (settings as { contact?: { phone?: string | null } } | null)?.contact?.phone || "17797",
  );
  const whatsapp = String(
    (settings as { contact?: { whatsapp?: string | null } } | null)?.contact?.whatsapp || "",
  );

  useLayoutEffect(() => {
    navigation.setOptions({ title: t("support.title") });
  }, [navigation, t]);

  const loadCurrent = useCallback(async () => {
    if (!enabled) return;
    setLoading(true);
    setError(null);
    try {
      let uuid = await getActiveConversationUuid();
      if (uuid) {
        try {
          const { data } = await getSupportConversation(uuid, token, locale);
          setConversation(data);
          await setActiveConversationUuid(data.uuid);
          return;
        } catch (e) {
          if (!(e instanceof ApiError && e.status === 404)) throw e;
          await setActiveConversationUuid(null);
        }
      }
      const listed = await listSupportConversations(token, locale, 1);
      const first = listed.data.conversations[0];
      if (first) {
        const { data } = await getSupportConversation(first.uuid, token, locale);
        setConversation(data);
        await setActiveConversationUuid(data.uuid);
      } else {
        setConversation(null);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : t("support.errorGeneric"));
    } finally {
      setLoading(false);
    }
  }, [enabled, token, locale, t]);

  const loadHistory = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await listSupportConversations(token, locale, 1);
      setHistory(data.conversations);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("support.errorGeneric"));
    } finally {
      setLoading(false);
    }
  }, [token, locale, t]);

  useEffect(() => {
    if (!enabled) return;
    if (tab === "current") void loadCurrent();
    else void loadHistory();
  }, [tab, enabled, loadCurrent, loadHistory]);

  const ensureConversation = async (): Promise<string> => {
    if (conversation?.uuid && conversation.status === "active") {
      return conversation.uuid;
    }
    const { data } = await createSupportConversation(token, locale);
    setConversation(data);
    await setActiveConversationUuid(data.uuid);
    return data.uuid;
  };

  const onSend = async () => {
    const text = draft.trim();
    if (!text || sending) return;
    setSending(true);
    setError(null);
    try {
      const uuid = await ensureConversation();
      const { data } = await sendSupportMessage(uuid, token, locale, text);
      setConversation(data);
      await setActiveConversationUuid(data.uuid);
      setDraft("");
      setTab("current");
    } catch (e) {
      setError(e instanceof Error ? e.message : t("support.errorGeneric"));
    } finally {
      setSending(false);
    }
  };

  const onNewChat = async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await createSupportConversation(token, locale);
      setConversation(data);
      await setActiveConversationUuid(data.uuid);
      setTab("current");
    } catch (e) {
      setError(e instanceof Error ? e.message : t("support.errorGeneric"));
    } finally {
      setLoading(false);
    }
  };

  const openHistoryItem = async (uuid: string) => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await getSupportConversation(uuid, token, locale);
      setConversation(data);
      await setActiveConversationUuid(data.uuid);
      setTab("current");
    } catch (e) {
      setError(e instanceof Error ? e.message : t("support.errorGeneric"));
    } finally {
      setLoading(false);
    }
  };

  const onEscalate = async () => {
    if (!token || !conversation?.uuid) return;
    setSending(true);
    setError(null);
    try {
      const { data } = await escalateSupportConversation(conversation.uuid, token, locale);
      setConversation(data.conversation);
      setError(`${data.message} (${data.reference_no})`);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("support.errorGeneric"));
    } finally {
      setSending(false);
    }
  };

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("support.unavailable")}</Text>
      </Screen>
    );
  }

  const messages = (conversation?.messages || []).filter(
    (m) => m.role === "user" || m.role === "assistant",
  );

  return (
    <Screen padded={false} avoidKeyboard>
      <View style={styles.tabs}>
        <Pressable
          style={[styles.tab, tab === "current" ? { borderBottomColor: accent } : null]}
          onPress={() => setTab("current")}
        >
          <Text style={styles.tabText}>{t("support.tabCurrent")}</Text>
        </Pressable>
        <Pressable
          style={[styles.tab, tab === "history" ? { borderBottomColor: accent } : null]}
          onPress={() => setTab("history")}
        >
          <Text style={styles.tabText}>{t("support.tabHistory")}</Text>
        </Pressable>
      </View>

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {tab === "history" ? (
        <ScrollView contentContainerStyle={styles.pad}>
          <PrimaryButton label={t("support.newChat")} onPress={() => void onNewChat()} />
          {loading ? <ActivityIndicator style={{ marginTop: 16 }} /> : null}
          {!loading && history.length === 0 ? (
            <Text style={styles.muted}>{t("support.emptyHistory")}</Text>
          ) : null}
          {history.map((item) => (
            <Pressable
              key={item.uuid}
              style={styles.historyItem}
              onPress={() => void openHistoryItem(item.uuid)}
            >
              <Text style={styles.historyTitle}>{item.title || t("support.untitled")}</Text>
              <Text style={styles.muted}>
                {item.last_message_at
                  ? formatSupportDateTime(item.last_message_at, locale)
                  : item.status}
              </Text>
            </Pressable>
          ))}
        </ScrollView>
      ) : (
        <View style={styles.fill}>
          <ScrollView contentContainerStyle={styles.messages}>
            {loading && !conversation ? <ActivityIndicator /> : null}
            {!loading && messages.length === 0 ? (
              <Text style={styles.muted}>{t("support.emptyCurrent")}</Text>
            ) : null}
            {messages.map((m) => (
              <View
                key={m.id}
                style={[
                  styles.bubble,
                  m.role === "user" ? styles.bubbleUser : styles.bubbleAssistant,
                ]}
              >
                <Text>{m.content}</Text>
              </View>
            ))}
          </ScrollView>
          <View style={styles.composer}>
            <TextInput
              style={styles.input}
              value={draft}
              onChangeText={setDraft}
              placeholder={t("support.placeholder")}
              multiline
              editable={!sending}
            />
            <PrimaryButton
              label={sending ? t("support.sending") : t("support.send")}
              disabled={sending}
              onPress={() => void onSend()}
            />
          </View>
        </View>
      )}

      <View style={styles.handoff}>
        <Pressable onPress={() => void Linking.openURL(`tel:${phone}`)}>
          <Text style={[styles.link, { color: accent }]}>{t("support.call")}</Text>
        </Pressable>
        {whatsapp ? (
          <Pressable
            onPress={() =>
              void Linking.openURL(`https://wa.me/${whatsapp.replace(/\D+/g, "")}`)
            }
          >
            <Text style={[styles.link, { color: accent }]}>{t("support.whatsapp")}</Text>
          </Pressable>
        ) : null}
        {token && conversation?.escalation_available ? (
          <Pressable onPress={() => void onEscalate()}>
            <Text style={[styles.link, { color: accent }]}>{t("support.escalate")}</Text>
          </Pressable>
        ) : null}
        <Pressable onPress={() => void onNewChat()}>
          <Text style={[styles.link, { color: accent }]}>{t("support.newChat")}</Text>
        </Pressable>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1 },
  tabs: { flexDirection: "row", borderBottomWidth: 1, borderBottomColor: "#e2ddd3" },
  tab: {
    flex: 1,
    paddingVertical: 12,
    alignItems: "center",
    borderBottomWidth: 2,
    borderBottomColor: "transparent",
  },
  tabText: { fontWeight: "700" },
  error: { color: "#991b1b", backgroundColor: "#fef2f2", padding: 10 },
  pad: { padding: 16, gap: 10 },
  muted: { color: "#666", marginTop: 12 },
  historyItem: {
    borderWidth: 1,
    borderColor: "#e2ddd3",
    borderRadius: 8,
    padding: 12,
    marginTop: 8,
    backgroundColor: "#fff",
  },
  historyTitle: { fontWeight: "600", marginBottom: 4 },
  messages: { padding: 16, gap: 8, flexGrow: 1 },
  bubble: { maxWidth: "90%", padding: 10, borderRadius: 10 },
  bubbleUser: { alignSelf: "flex-end", backgroundColor: "#e6f4ef" },
  bubbleAssistant: { alignSelf: "flex-start", backgroundColor: "#f3f1ec" },
  composer: { padding: 12, borderTopWidth: 1, borderTopColor: "#e2ddd3", gap: 8 },
  input: {
    minHeight: 64,
    borderWidth: 1,
    borderColor: "#d5d0c6",
    borderRadius: 8,
    padding: 10,
    backgroundColor: "#fff",
    textAlignVertical: "top",
  },
  handoff: {
    flexDirection: "row",
    flexWrap: "wrap",
    gap: 12,
    padding: 12,
    borderTopWidth: 1,
    borderTopColor: "#e2ddd3",
    backgroundColor: "#faf8f4",
  },
  link: { fontSize: 13, textDecorationLine: "underline" },
});
