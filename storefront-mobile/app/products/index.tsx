import { useState } from "react";
import { Stack } from "expo-router";
import { StyleSheet } from "react-native";
import { useApp } from "../../src/contexts/AppContext";
import { FormTextInput } from "../../src/components/FormTextInput";
import { ProductGridScreen } from "../../src/components/catalog/ProductGridScreen";
import { Screen } from "../../src/components/ui";

export default function ProductsIndexScreen() {
  const { locale, t } = useApp();
  const [q, setQ] = useState("");

  return (
    <Screen padded={false}>
      <Stack.Screen options={{ title: t("nav.shop") }} />
      <ProductGridScreen
        locale={locale}
        mode={q.trim().length >= 2 ? "search" : "products"}
        searchQ={q}
        header={
          <FormTextInput
            value={q}
            onChangeText={setQ}
            placeholder={t("common.search")}
            style={styles.input}
            autoCapitalize="none"
          />
        }
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  input: { marginBottom: 12, marginTop: 8 },
});
