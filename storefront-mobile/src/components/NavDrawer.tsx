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

type Tab = "menu" | "categories";

type Props = {
  visible: boolean;
  onClose: () => void;
};

function navigateHref(
  router: ReturnType<typeof useRouter>,
  href: string,
  external?: boolean,
) {
  if (external || /^https?:\/\//i.test(href)) {
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
 * Side drawer: Menu (Qwik main nav) + Categories tabs, plus language switcher.
 */
export function NavDrawer({ visible, onClose }: Props) {
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const { t, locale, setLocale, accent, settings } = useApp();
  const { isRtl, row, textAlign, writingDirection } = useRtl();
  const [tab, setTab] = useState<Tab>("menu");
  const [categories, setCategories] = useState<Category[]>([]);
  const [expanded, setExpanded] = useState<string | null>(null);

  const navItems = useMemo(
    () =>
      buildMainNavLinks(locale, {
        digitalEnabled: settings?.digital?.enabled !== false,
      }),
    [locale, settings?.digital?.enabled],
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
    }
  }, [visible, loadCategories]);

  const go = (href: string, external?: boolean) => {
    onClose();
    // Slight delay so modal closes before navigation.
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
                key={child.href}
                style={[styles.childItem, { flexDirection: row }]}
                onPress={() => go(child.href)}
              >
                <Text
                  style={[styles.childText, { textAlign, writingDirection }]}
                >
                  {child.label}
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

          <View style={[styles.tabs, { flexDirection: row }]}>
            {(
              [
                ["menu", t("nav.menuTab")],
                ["categories", t("nav.categoriesTab")],
              ] as const
            ).map(([id, label]) => {
              const active = tab === id;
              return (
                <Pressable
                  key={id}
                  style={[
                    styles.tab,
                    active && {
                      borderBottomColor: accent,
                      borderBottomWidth: 2,
                    },
                  ]}
                  onPress={() => setTab(id)}
                >
                  <Text
                    style={[
                      styles.tabText,
                      active && { color: accent, fontWeight: "800" },
                      { textAlign: "center" },
                    ]}
                  >
                    {label}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          <ScrollView
            style={styles.body}
            contentContainerStyle={{ paddingBottom: 24 }}
          >
            {tab === "menu"
              ? navItems.map(renderMenuItem)
              : categories.map((cat) => (
                  <Pressable
                    key={cat.id}
                    style={[styles.item, { flexDirection: row }]}
                    onPress={() => go(`/category/${cat.slug}`)}
                  >
                    <Text
                      style={[
                        styles.itemText,
                        { textAlign, writingDirection, flex: 1 },
                      ]}
                    >
                      {cat.name}
                    </Text>
                  </Pressable>
                ))}
            {tab === "categories" && categories.length === 0 ? (
              <Text style={[styles.empty, { textAlign }]}>
                {t("nav.emptyCategories")}
              </Text>
            ) : null}
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
                        active && styles.langBtnActive,
                      ]}
                    >
                      {code === "en" ? "English" : "العربية"}
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
    position: "absolute",
    top: 0,
    bottom: 0,
    width: "86%",
    maxWidth: 360,
    backgroundColor: "#F7F7F5",
    paddingHorizontal: 16,
  },
  panelLtr: {
    left: 0,
    borderTopRightRadius: 16,
    borderBottomRightRadius: 16,
  },
  panelRtl: {
    right: 0,
    borderTopLeftRadius: 16,
    borderBottomLeftRadius: 16,
  },
  panelHead: {
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 12,
  },
  panelTitle: { fontSize: 20, fontWeight: "800", color: "#111" },
  tabs: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: "#ddd" },
  tab: { flex: 1, paddingVertical: 12 },
  tabText: { color: "#666", fontWeight: "600" },
  body: { flex: 1, marginTop: 8 },
  item: {
    alignItems: "center",
    justifyContent: "space-between",
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#e8e8e8",
    gap: 8,
  },
  itemText: { fontSize: 16, fontWeight: "600", color: "#222" },
  childItem: {
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#f0f0f0",
  },
  childText: { fontSize: 15, color: "#555" },
  empty: { color: "#888", padding: 16 },
  langBlock: {
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "#ddd",
    paddingTop: 14,
  },
  langLabel: { fontWeight: "700", marginBottom: 10, color: "#333" },
  langRow: { gap: 8 },
  langBtn: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: 10,
    backgroundColor: "#e8e8e8",
    alignItems: "center",
  },
  langBtnText: { fontWeight: "700", color: "#333" },
  langBtnActive: { color: "#111" },
});
