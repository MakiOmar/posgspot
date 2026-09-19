import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useRouter } from "expo-router";
import { useState } from "react";
import {
  Linking,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useApp } from "../contexts/AppContext";
import { useRtl } from "../lib/rtl";

const HOTLINE = "17797";

/**
 * Floating contact FAB: Call us / Chat with agent / Leave us a message.
 */
export function ContactFab() {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { t, accent, settings } = useApp();
  const { isRtl } = useRtl();
  const [open, setOpen] = useState(false);

  const phone = settings?.contact?.phone?.trim() || HOTLINE;
  const phoneHref = `tel:${phone.replace(/[^\d+]/g, "") || HOTLINE}`;
  const chatEnabled = Boolean(settings?.support_chat?.enabled);

  return (
    <View
      pointerEvents="box-none"
      style={[
        styles.wrap,
        {
          bottom: Math.max(insets.bottom, 12) + 64,
          ...(isRtl ? { left: 16 } : { right: 16 }),
        },
      ]}
    >
      {open ? (
        <View style={styles.menu}>
          <Pressable
            style={styles.menuItem}
            onPress={() => {
              setOpen(false);
              void Linking.openURL(phoneHref);
            }}
          >
            <FontAwesome name="phone" size={16} color="#111" />
            <Text style={styles.menuText}>{t("contact.callUs")}</Text>
          </Pressable>
          <Pressable
            style={[styles.menuItem, !chatEnabled ? styles.menuItemDisabled : null]}
            disabled={!chatEnabled}
            onPress={() => {
              if (!chatEnabled) return;
              setOpen(false);
              router.push("/support" as never);
            }}
          >
            <FontAwesome name="comments" size={16} color="#111" />
            <Text style={styles.menuText}>
              {chatEnabled ? t("contact.liveChat") : t("contact.chatUnavailable")}
            </Text>
          </Pressable>
          <Pressable
            style={styles.menuItem}
            onPress={() => {
              setOpen(false);
              router.push("/contact" as never);
            }}
          >
            <FontAwesome name="envelope" size={16} color="#111" />
            <Text style={styles.menuText}>{t("contact.leaveMessage")}</Text>
          </Pressable>
        </View>
      ) : null}

      <Pressable
        accessibilityRole="button"
        accessibilityLabel={open ? t("contact.close") : t("contact.open")}
        style={[styles.fab, { backgroundColor: accent || "#00d4aa" }]}
        onPress={() => setOpen((v) => !v)}
      >
        <FontAwesome name={open ? "times" : "headphones"} size={22} color="#041510" />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    position: "absolute",
    zIndex: 50,
    alignItems: "flex-end",
    gap: 10,
  },
  fab: {
    width: 56,
    height: 56,
    borderRadius: 28,
    alignItems: "center",
    justifyContent: "center",
    elevation: 6,
    shadowColor: "#000",
    shadowOpacity: 0.25,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
  },
  menu: {
    backgroundColor: "#fff",
    borderRadius: 14,
    padding: 8,
    gap: 4,
    minWidth: 200,
    elevation: 5,
    shadowColor: "#000",
    shadowOpacity: 0.2,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 3 },
  },
  menuItem: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 12,
    borderRadius: 10,
  },
  menuItemDisabled: {
    opacity: 0.45,
  },
  menuText: {
    color: "#111",
    fontSize: 15,
    fontWeight: "600",
    flexShrink: 1,
  },
});
