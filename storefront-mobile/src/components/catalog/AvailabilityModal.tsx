import { ActivityIndicator, Modal, Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import type { ProductAvailability } from "../../lib/types";

type Props = {
  open: boolean;
  loading: boolean;
  error: string | null;
  availability: ProductAvailability | null;
  onClose: () => void;
};

export function AvailabilityModal({
  open,
  loading,
  error,
  availability,
  onClose,
}: Props) {
  const { t, accent } = useApp();
  const locations = availability?.locations || [];

  return (
    <Modal visible={open} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.sheet}>
          <View style={styles.header}>
            <Text style={styles.title}>{t("catalog.checkAvailability")}</Text>
            <Pressable onPress={onClose} hitSlop={12}>
              <Text style={styles.close}>✕</Text>
            </Pressable>
          </View>
          {loading ? (
            <View style={styles.loading}>
              <ActivityIndicator color={accent} />
              <Text style={styles.empty}>{t("common.loading")}</Text>
            </View>
          ) : null}
          {error ? <Text style={styles.error}>{error}</Text> : null}
          {!loading && !error ? (
            <ScrollView style={styles.list}>
              {locations.length === 0 ? (
                <Text style={styles.empty}>{t("availability.empty")}</Text>
              ) : (
                locations.map((loc) => (
                  <View key={String(loc.id ?? loc.name)} style={styles.row}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.locName}>{loc.name}</Text>
                      {loc.address ? (
                        <Text style={styles.locMeta}>{loc.address}</Text>
                      ) : null}
                    </View>
                    <Text
                      style={{
                        fontWeight: "700",
                        color: loc.in_stock ? accent : "#B00020",
                      }}
                    >
                      {loc.in_stock
                        ? t("catalog.inStock")
                        : t("catalog.outOfStock")}
                    </Text>
                  </View>
                ))
              )}
            </ScrollView>
          ) : null}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.4)",
    justifyContent: "flex-end",
  },
  sheet: {
    backgroundColor: "#fff",
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    maxHeight: "70%",
    paddingBottom: 24,
  },
  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#eee",
  },
  title: { fontSize: 17, fontWeight: "800", color: "#111" },
  close: { fontSize: 18, color: "#666", paddingHorizontal: 4 },
  list: { paddingHorizontal: 16, paddingTop: 8 },
  row: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#f0f0f0",
  },
  locName: { fontWeight: "700", color: "#222" },
  locMeta: { color: "#888", fontSize: 12, marginTop: 2 },
  empty: { color: "#666", textAlign: "center", paddingVertical: 24 },
  loading: { alignItems: "center", gap: 8, paddingVertical: 32 },
  error: { color: "#B00020", textAlign: "center", padding: 16 },
});
