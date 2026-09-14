import { Pressable, StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

export function UnderlineTabs<T extends string>({
  items,
  value,
  onChange,
}: {
  items: Array<{ id: T; label: string }>;
  value: T;
  onChange: (id: T) => void;
}) {
  const { accent } = useApp();
  const { row } = useRtl();

  return (
    <View style={[styles.tabs, { flexDirection: row }]}>
      {items.map((item) => {
        const active = value === item.id;
        return (
          <Pressable
            key={item.id}
            style={[
              styles.tab,
              active && { borderBottomColor: accent, borderBottomWidth: 2 },
            ]}
            onPress={() => onChange(item.id)}
          >
            <Text
              numberOfLines={1}
              style={[
                styles.tabText,
                active && { color: accent, fontWeight: "800" },
              ]}
            >
              {item.label}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  tabs: {
    borderBottomWidth: 1,
    borderBottomColor: "#e5e5e5",
  },
  tab: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 4,
    alignItems: "center",
  },
  tabText: { color: "#666", fontWeight: "600", fontSize: 13 },
});
