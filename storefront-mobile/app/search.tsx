import { useState } from "react";
import { StyleSheet } from "react-native";
import { useApp } from "../src/contexts/AppContext";
import { FormTextInput } from "../src/components/FormTextInput";
import { ProductGridScreen } from "../src/components/catalog/ProductGridScreen";
import { Screen } from "../src/components/ui";

export default function SearchScreen() {
  const { locale, t } = useApp();
  const [q, setQ] = useState("");

  return (
    <Screen padded={false}>
      <ProductGridScreen
        locale={locale}
        mode="search"
        searchQ={q}
        header={
          <FormTextInput
            style={styles.input}
            placeholder={t("common.search")}
            value={q}
            onChangeText={setQ}
            autoFocus
            autoCapitalize="none"
            returnKeyType="search"
          />
        }
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  input: { marginBottom: 12, marginTop: 8 },
});
