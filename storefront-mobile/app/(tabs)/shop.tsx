import { Stack } from "expo-router";
import { StyleSheet, View } from "react-native";
import { useApp } from "../../src/contexts/AppContext";
import { StorefrontHeader } from "../../src/components/StorefrontHeader";
import { ProductGridScreen } from "../../src/components/catalog/ProductGridScreen";
import { Screen } from "../../src/components/ui";

export default function ShopScreen() {
  const { locale } = useApp();

  return (
    <Screen padded={false}>
      <StorefrontHeader />
      <View style={styles.body}>
        <ProductGridScreen locale={locale} />
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1 },
});
