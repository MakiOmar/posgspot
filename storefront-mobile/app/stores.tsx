import { useCallback, useEffect, useState } from "react";
import { FlatList, Linking, Text, View } from "react-native";
import { fetchLocations } from "../src/lib/api";
import type { StoreLocation } from "../src/lib/types";
import { useApp } from "../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../src/components/ui";

export default function StoresScreen() {
  const { locale, t } = useApp();
  const [locations, setLocations] = useState<StoreLocation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchLocations(false, locale);
      setLocations(data || []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        data={locations}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <View
            style={{
              backgroundColor: "#fff",
              padding: 14,
              borderRadius: 12,
              marginBottom: 10,
              gap: 8,
            }}
          >
            <Text style={{ fontWeight: "800", fontSize: 16 }}>{item.name}</Text>
            {item.address ? <Text>{item.address}</Text> : null}
            {item.mobile ? (
              <PrimaryButton
                label={`Call ${item.mobile}`}
                onPress={() => void Linking.openURL(`tel:${item.mobile}`)}
              />
            ) : null}
            {item.maps_url ? (
              <PrimaryButton
                label="Directions"
                onPress={() => void Linking.openURL(item.maps_url!)}
              />
            ) : null}
          </View>
        )}
        ListEmptyComponent={<Text>No stores found</Text>}
      />
    </Screen>
  );
}
