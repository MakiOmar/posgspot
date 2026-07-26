import type { ComponentProps } from "react";
import { Pressable, StyleSheet, Text } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

type IconName = ComponentProps<typeof Ionicons>["name"];

/** Chevron row for the account hub menu. */
export function AccountMenuRow({
  icon,
  label,
  onPress,
  danger,
}: {
  icon: IconName;
  label: string;
  onPress: () => void;
  danger?: boolean;
}) {
  const { accent } = useApp();
  const { row, textAlign, writingDirection, isRtl } = useRtl();
  const color = danger ? "#B00020" : accent;

  return (
    <Pressable
      style={[styles.row, { flexDirection: row }]}
      onPress={onPress}
    >
      <Ionicons name={icon} size={22} color={color} style={styles.icon} />
      <Text
        style={[
          styles.label,
          { textAlign, writingDirection, color: danger ? "#B00020" : "#111" },
        ]}
      >
        {label}
      </Text>
      <Ionicons
        name={isRtl ? "chevron-back" : "chevron-forward"}
        size={18}
        color={color}
      />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  row: {
    alignItems: "center",
    paddingVertical: 16,
    paddingHorizontal: 4,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#e5e5e5",
    gap: 12,
  },
  icon: { width: 28 },
  label: { flex: 1, fontSize: 16, fontWeight: "500" },
});
