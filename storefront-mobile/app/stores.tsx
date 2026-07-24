import { useCallback, useEffect, useState } from "react";
import {
  FlatList,
  Linking,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { fetchLocations } from "../src/lib/api";
import type { StoreLocation } from "../src/lib/types";
import { useApp } from "../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../src/components/ui";

function mapsLink(loc: StoreLocation): string | null {
  if (loc.maps_url) {
    return loc.maps_url;
  }
  if (loc.latitude != null && loc.longitude != null) {
    return `https://www.google.com/maps/search/?api=1&query=${loc.latitude},${loc.longitude}`;
  }
  if (loc.address) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(loc.address)}`;
  }
  return null;
}

export default function StoresScreen() {
  const { locale, t, accent } = useApp();
  const [locations, setLocations] = useState<StoreLocation[]>([]);
  const [sellingOnly, setSellingOnly] = useState(false);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await fetchLocations(sellingOnly, locale);
      setLocations(data || []);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [locale, t, sellingOnly]);

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
      <Pressable
        style={[
          styles.filter,
          sellingOnly && { borderColor: accent, backgroundColor: "#fff8e8" },
        ]}
        onPress={() => setSellingOnly((v) => !v)}
      >
        <Text style={styles.filterText}>
          {sellingOnly
            ? `✓ ${t("stores.sellingOnly")}`
            : t("stores.sellingOnly")}
        </Text>
      </Pressable>
      {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
      <FlatList
        style={{ flex: 1 }}
        data={locations}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => {
          const mapUrl = mapsLink(item);
          return (
            <View style={styles.card}>
              <Text style={styles.name}>{item.name}</Text>
              {item.address ? <Text style={styles.meta}>{item.address}</Text> : null}
              {item.is_selling_location ? (
                <Text style={styles.badge}>{t("stores.selling")}</Text>
              ) : null}
              {item.enable_pickup ? (
                <Text style={styles.badge}>{t("stores.pickup")}</Text>
              ) : null}
              {item.mobile ? (
                <PrimaryButton
                  label={`${t("stores.call")} ${item.mobile}`}
                  onPress={() => void Linking.openURL(`tel:${item.mobile}`)}
                />
              ) : null}
              {mapUrl ? (
                <PrimaryButton
                  label={t("stores.directions")}
                  onPress={() => void Linking.openURL(mapUrl)}
                />
              ) : null}
            </View>
          );
        }}
        ListEmptyComponent={<Text>{t("stores.empty")}</Text>}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  filter: {
    alignSelf: "flex-start",
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#ddd",
    backgroundColor: "#fff",
    marginBottom: 12,
  },
  filterText: { fontWeight: "600" },
  card: {
    backgroundColor: "#fff",
    padding: 14,
    borderRadius: 12,
    marginBottom: 10,
    gap: 8,
  },
  name: { fontWeight: "800", fontSize: 16 },
  meta: { color: "#555" },
  badge: { color: "#666", fontSize: 13 },
});
