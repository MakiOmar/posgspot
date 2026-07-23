import { FlatList, StyleSheet, Text, View } from "react-native";
import { Link } from "expo-router";
import { useCart } from "../../src/contexts/CartContext";
import { useApp } from "../../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../../src/components/ui";

export default function CartScreen() {
  const { t } = useApp();
  const { items, subtotal, updateQty, removeItem, count } = useCart();

  if (!count) {
    return (
      <Screen>
        <View style={styles.center}>
          <Text style={styles.empty}>{t("cart.empty")}</Text>
          <Link href="/(tabs)/shop" asChild>
            <PrimaryButton label={t("nav.shop")} onPress={() => undefined} />
          </Link>
        </View>
      </Screen>
    );
  }

  return (
    <Screen>
      <FlatList
        data={items}
        keyExtractor={(item) =>
          item.digital?.line_key || String(item.variationId)
        }
        renderItem={({ item }) => (
          <View style={styles.row}>
            <View style={{ flex: 1 }}>
              <Text style={styles.name}>{item.name}</Text>
              <Text>
                {(item.unitPrice * item.quantity).toFixed(2)} EGP
              </Text>
            </View>
            {!item.digital ? (
              <View style={styles.qtyRow}>
                <PrimaryButton
                  label="-"
                  onPress={() =>
                    void updateQty(item.variationId, item.quantity - 1)
                  }
                />
                <Text style={styles.qty}>{item.quantity}</Text>
                <PrimaryButton
                  label="+"
                  onPress={() =>
                    void updateQty(item.variationId, item.quantity + 1)
                  }
                />
              </View>
            ) : null}
            <PrimaryButton
              label="×"
              onPress={() =>
                void removeItem(item.variationId, item.digital?.line_key)
              }
            />
          </View>
        )}
      />
      <Text style={styles.subtotal}>
        {t("cart.subtotal")}: {subtotal.toFixed(2)} EGP
      </Text>
      <Link href="/checkout" asChild>
        <PrimaryButton label={t("common.checkout")} onPress={() => undefined} />
      </Link>
    </Screen>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, justifyContent: "center", gap: 16 },
  empty: { textAlign: "center", fontSize: 16, color: "#666" },
  row: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 12,
    marginBottom: 10,
    gap: 8,
  },
  name: { fontWeight: "700", marginBottom: 4 },
  qtyRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  qty: { minWidth: 24, textAlign: "center", fontWeight: "700" },
  subtotal: { fontSize: 18, fontWeight: "800", marginVertical: 12 },
});
