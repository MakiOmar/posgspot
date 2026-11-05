import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import type { GiftCardBrandGroup } from "../../lib/gift-card-groups";
import { brandDisplayLabel, countryDisplayLabel } from "../../lib/gift-card-labels";
import { useRtl } from "../../lib/rtl";
import { RemoteImage } from "../RemoteImage";

/** Country picker for a gift card brand: flag, name, card count, plus "all brand cards". */
export function GiftCardCountrySheet({
  group,
  onChoose,
  onClose,
}: {
  /** Brand whose countries are listed; null keeps the sheet closed. */
  group: GiftCardBrandGroup | null;
  onChoose: (brand: string, country: string | null) => void;
  onClose: () => void;
}) {
  const { t, locale, accent } = useApp();
  const { row, textAlign, writingDirection } = useRtl();
  const brandLabel = group ? brandDisplayLabel(locale, group.brand, group.label) : "";

  return (
    <Modal visible={!!group} animationType="slide" transparent onRequestClose={onClose}>
      <Pressable style={styles.backdrop} onPress={onClose}>
        <Pressable style={styles.sheet} onPress={() => undefined}>
          <View style={[styles.header, { flexDirection: row }]}>
            <Text style={[styles.title, { textAlign, writingDirection }]}>
              {t("digital.chooseCountryFor", { brand: brandLabel })}
            </Text>
            <Pressable
              onPress={onClose}
              hitSlop={12}
              accessibilityRole="button"
              accessibilityLabel={t("common.close")}
            >
              <Text style={styles.close}>✕</Text>
            </Pressable>
          </View>
          {group ? (
            <ScrollView style={styles.list}>
              {group.countries.map((country) => (
                <Pressable
                  key={country.key}
                  style={[styles.row, { flexDirection: row }]}
                  onPress={() => onChoose(group.brand, country.key)}
                  accessibilityRole="button"
                >
                  {country.flag_url ? (
                    <RemoteImage uri={country.flag_url} style={styles.flag} />
                  ) : (
                    <View style={[styles.flag, styles.flagBlank]} />
                  )}
                  <Text style={[styles.label, { textAlign, writingDirection }]}>
                    {countryDisplayLabel(locale, country.key, country.label)}
                  </Text>
                  <Text style={styles.count}>{country.count}</Text>
                </Pressable>
              ))}
              <Pressable
                style={[styles.allBtn, { borderColor: accent }]}
                onPress={() => onChoose(group.brand, null)}
                accessibilityRole="button"
              >
                <Text style={{ color: accent, fontWeight: "700" }}>
                  {t("digital.allBrandCards", { brand: brandLabel })}
                </Text>
              </Pressable>
            </ScrollView>
          ) : null}
        </Pressable>
      </Pressable>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: "rgba(0,0,0,0.4)", justifyContent: "flex-end" },
  sheet: {
    backgroundColor: "#fff",
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    maxHeight: "75%",
    paddingBottom: 24,
  },
  header: {
    alignItems: "center",
    justifyContent: "space-between",
    gap: 12,
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#eee",
  },
  title: { flex: 1, fontSize: 17, fontWeight: "800", color: "#111" },
  close: { fontSize: 18, color: "#666", paddingHorizontal: 4 },
  list: { paddingHorizontal: 16, paddingTop: 8 },
  row: {
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#f0f0f0",
  },
  flag: { width: 28, height: 20, borderRadius: 3 },
  flagBlank: { backgroundColor: "#e5e7eb" },
  label: { flex: 1, fontWeight: "600", color: "#222" },
  count: { color: "#666", fontWeight: "700" },
  allBtn: {
    marginTop: 16,
    alignItems: "center",
    paddingVertical: 12,
    borderWidth: 1,
    borderRadius: 10,
  },
});
