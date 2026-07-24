import { FlatList, StyleSheet, Text, View } from "react-native";
import { useRouter } from "expo-router";
import { useApp } from "../../src/contexts/AppContext";
import { useWishlist } from "../../src/contexts/WishlistContext";
import {
  LoadingBlock,
  PrimaryButton,
  ProductCard,
  Screen,
} from "../../src/components/ui";

export default function WishlistTabScreen() {
  const { t, token } = useApp();
  const router = useRouter();
  const { items, count, hydrated, refresh } = useWishlist();

  if (!hydrated) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen>
      <Text style={styles.meta}>
        {count} {t("common.wishlist").toLowerCase()}
        {!token ? ` · ${t("account.guest")}` : ""}
      </Text>
      {!token ? (
        <View style={styles.guestBanner}>
          <Text style={styles.hint}>{t("wishlist.signInHint")}</Text>
          <PrimaryButton
            label={t("common.login")}
            onPress={() => router.push("/login")}
          />
        </View>
      ) : null}
      <FlatList
        style={styles.list}
        data={items}
        keyExtractor={(item) => String(item.id)}
        numColumns={2}
        columnWrapperStyle={styles.grid}
        renderItem={({ item }) => <ProductCard product={item} />}
        ListEmptyComponent={
          <Text style={styles.hint}>{t("wishlist.empty")}</Text>
        }
        onRefresh={() => void refresh().catch(() => undefined)}
        refreshing={false}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  meta: { marginBottom: 8, color: "#666", fontWeight: "600" },
  guestBanner: { marginBottom: 12, gap: 8 },
  hint: { color: "#666", textAlign: "center", marginVertical: 8 },
  list: { flex: 1 },
  grid: { justifyContent: "space-between" },
});
