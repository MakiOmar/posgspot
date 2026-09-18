import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useRouter } from "expo-router";
import { useCallback, useEffect, useMemo, useState } from "react";
import {
  Linking,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { fetchCategories } from "../lib/api";
import { buildMainNavLinks, type MainNavItem } from "../lib/main-nav";
import { useRtl } from "../lib/rtl";
import type { Category } from "../lib/types";
import { useApp } from "../contexts/AppContext";

type Props = {
  visible: boolean;
  onClose: () => void;
};

function navigateHref(
  router: ReturnType<typeof useRouter>,
  href: string,
  external?: boolean,
) {
  if (href.startsWith("tel:") || external || /^https?:\/\//i.test(href)) {
    void Linking.openURL(href);
    return;
  }
  if (href.includes("?")) {
    const [path, qs] = href.split("?");
    const params = Object.fromEntries(new URLSearchParams(qs));
    router.push({ pathname: path as never, params });
    return;
  }
  router.push(href as never);
}

/**
 * Side drawer: main nav (Shop children include console categories) + language.
 */
export function NavDrawer({ visible, onClose }: Props) {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { t, locale, setLocale, accent, settings, refreshSettings } = useApp();
  const { isRtl, row, textAlign, writingDirection } = useRtl();
  const [categories, setCategories] = useState<Category[]>([]);
  const [expanded, setExpanded] = useState<string | null>(null);

  const navItems = useMemo(
    () =>
      buildMainNavLinks(locale, {
        digitalEnabled: settings?.digital?.enabled !== false,
        categories,
        supportChatEnabled: settings
          ? Boolean(settings.support_chat?.enabled)
          : true,
        customBundleEnabled: settings
          ? Boolean(settings.custom_bundle?.enabled)
          : true,
        sellToUsEnabled: settings
          ? Boolean(settings.sell_to_us?.enabled)
          : true,
        communityEnabled: Boolean(settings?.community?.enabled),
        requestProductEnabled: Boolean(settings?.request_product?.enabled),
      }),
    [locale, settings, categories],
  );

  const loadCategories = useCallback(async () => {
    try {
      const { data } = await fetchCategories(locale);
      setCategories(data || []);
    } catch {
      setCategories([]);
    }
  }, [locale]);

  useEffect(() => {
    if (visible) {
      void loadCategories();
      if (!settings) {
        void refreshSettings().catch(() => undefined);
      }
    }
  }, [visible, loadCategories, settings, refreshSettings]);

  const go = (href: string, external?: boolean) => {
    onClose();
    setTimeout(() => navigateHref(router, href, external), 50);
  };

  const renderMenuItem = (item: MainNavItem, index: number) => {
    const key = `${item.label}-${index}`;
    const hasChildren = !!item.children?.length;
    const isOpen = expanded === key;

    return (
      <View key={key}>
        <Pressable
          style={[styles.item, { flexDirection: row }]}
          onPress={() => {
            if (hasChildren) {
              setExpanded(isOpen ? null : key);
              return;
            }
            if (item.href) go(item.href, item.external);
          }}
        >
          <Text style={[styles.itemText, { textAlign, writingDirection, flex: 1 }]}>
            {item.label}
          </Text>
          {hasChildren ? (
            <FontAwesome
              name={isOpen ? "chevron-up" : "chevron-down"}
              size={14}
              color="#666"
            />
          ) : item.external ? (
            <FontAwesome name="external-link" size={14} color="#888" />
          ) : null}
        </Pressable>
        {hasChildren && isOpen
          ? item.children!.map((child) => (
              <Pressable
                key={child.href || child.label}
                style={[styles.childItem, { flexDirection: row }]}
                disabled={child.disabled || !child.href}
                onPress={() => {
                  if (child.disabled || !child.href) return;
                  go(child.href);
                }}
              >
                <Text
                  style={[
                    styles.childText,
                    { textAlign, writingDirection },
                    child.disabled ? styles.childDisabled : null,
                  ]}
                >
                  {child.label}
                  {child.hint ? ` (${child.hint})` : ""}
                </Text>
              </Pressable>
            ))
          : null}
      </View>
    );
  };

  return (
    <Modal
      visible={visible}
      animationType="fade"
      transparent
      onRequestClose={onClose}
    >
      <View style={styles.backdrop}>
        <Pressable style={styles.scrim} onPress={onClose} />
        <View
          style={[
            styles.panel,
            {
              paddingTop: Math.max(insets.top, 12),
              paddingBottom: Math.max(insets.bottom, 16),
            },
            isRtl ? styles.panelRtl : styles.panelLtr,
          ]}
        >
          <View style={[styles.panelHead, { flexDirection: row }]}>
            <Text style={[styles.panelTitle, { textAlign, writingDirection }]}>
              {t("nav.menu")}
            </Text>
            <Pressable onPress={onClose} hitSlop={12}>
              <FontAwesome name="times" size={22} color="#333" />
            </Pressable>
          </View>

          <ScrollView
            style={styles.body}
            contentContainerStyle={{ paddingBottom: 24 }}
          >
            {navItems.map(renderMenuItem)}
          </ScrollView>

          <View style={styles.langBlock}>
            <Text style={[styles.langLabel, { textAlign, writingDirection }]}>
              {t("nav.language")}
            </Text>
            <View style={[styles.langRow, { flexDirection: row }]}>
              {(["en", "ar"] as const).map((code) => {
                const active = locale === code;
                return (
                  <Pressable
                    key={code}
                    style={[
                      styles.langBtn,
                      active && { backgroundColor: accent },
                    ]}
                    onPress={() => setLocale(code)}
                  >
                    <Text
                      style={[
                        styles.langBtnText,
                        active && styles.langBtnTextActive,
                      ]}
                    >
                      {code.toUpperCase()}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
          </View>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, flexDirection: "row" },
  scrim: { flex: 1, backgroundColor: "rgba(0,0,0,0.45)" },
  panel: {
    width: "86%",
    maxWidth: 360,
    backgroundColor: "#F7F7F5",
    height: "100%",
  },
  panelLtr: { marginRight: "auto" },
  panelRtl: { marginLeft: "auto" },
  panelHead: {
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingBottom: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#ddd",
  },
  panelTitle: { fontSize: 18, fontWeight: "800", color: "#111" },
  body: { flex: 1, paddingHorizontal: 8, paddingTop: 8 },
  item: {
    alignItems: "center",
    paddingVertical: 14,
    paddingHorizontal: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#e8e8e8",
  },
  itemText: { fontSize: 16, fontWeight: "700", color: "#222" },
  childItem: {
    alignItems: "center",
    paddingVertical: 12,
    paddingHorizontal: 24,
    backgroundColor: "#fff",
  },
  childText: { fontSize: 15, color: "#333" },
  childDisabled: { color: "#999" },
  langBlock: {
    padding: 16,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "#ddd",
  },
  langLabel: { fontWeight: "700", marginBottom: 8, color: "#444" },
  langRow: { gap: 8 },
  langBtn: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: "#e8e8e8",
  },
  langBtnText: { fontWeight: "800", color: "#333" },
  langBtnTextActive: { color: "#111" },
});
