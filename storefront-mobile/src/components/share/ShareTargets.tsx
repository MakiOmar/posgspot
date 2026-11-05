import FontAwesome from "@expo/vector-icons/FontAwesome";
import * as Clipboard from "expo-clipboard";
import { Linking, Pressable, StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";
import { shareNative, shareTargetUrls } from "../../lib/share";
import { toast } from "../../lib/toast";

type Target = {
  key: string;
  icon: keyof typeof FontAwesome.glyphMap;
  label: string;
  onPress: () => void;
};

/** Share row (native, copy link, WhatsApp, Facebook, X) — same targets as the website PDP. */
export function ShareTargets({ title, url }: { title: string; url: string }) {
  const { t } = useApp();
  const { row, textAlign, writingDirection } = useRtl();
  const links = shareTargetUrls(title, url);

  const openExternal = (href: string) => {
    void Linking.openURL(href).catch(() => toast.error(t("share.failed")));
  };

  const targets: Target[] = [
    {
      key: "native",
      icon: "share-alt",
      label: t("share.native"),
      onPress: () =>
        void shareNative(title, url, t("share.label")).then((ok) => {
          if (!ok) toast.error(t("share.failed"));
        }),
    },
    {
      key: "copy",
      icon: "link",
      label: t("share.copy"),
      onPress: () =>
        void Clipboard.setStringAsync(url)
          .then(() => toast.success(t("share.copied")))
          .catch(() => toast.error(t("share.copyFailed"))),
    },
    { key: "whatsapp", icon: "whatsapp", label: t("share.whatsapp"), onPress: () => openExternal(links.whatsapp) },
    { key: "facebook", icon: "facebook", label: t("share.facebook"), onPress: () => openExternal(links.facebook) },
    { key: "x", icon: "twitter", label: t("share.x"), onPress: () => openExternal(links.x) },
  ];

  return (
    <View style={styles.wrap}>
      <Text style={[styles.label, { textAlign, writingDirection }]}>{t("share.label")}</Text>
      <View style={[styles.row, { flexDirection: row }]} accessibilityRole="toolbar">
        {targets.map((target) => (
          <Pressable
            key={target.key}
            onPress={target.onPress}
            style={styles.btn}
            accessibilityRole="button"
            accessibilityLabel={target.label}
            hitSlop={4}
          >
            <FontAwesome name={target.icon} size={18} color="#222" />
          </Pressable>
        ))}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 8 },
  label: { fontSize: 13, fontWeight: "700", color: "#666" },
  row: { gap: 10, flexWrap: "wrap" },
  btn: {
    width: 42,
    height: 42,
    borderRadius: 21,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#ddd",
  },
});
