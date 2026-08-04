import { useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";

type Props = {
  label: string;
  value: string;
  revealLabel: string;
  hideLabel: string;
  accent: string;
};

/** Masked secret with tap-to-reveal (passwords / digital codes). */
export function SecretRevealRow({
  label,
  value,
  revealLabel,
  hideLabel,
  accent,
}: Props) {
  const [open, setOpen] = useState(false);

  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}: </Text>
      <Text style={styles.value} selectable={open}>
        {open ? value : "••••••••"}
      </Text>
      <Pressable onPress={() => setOpen((v) => !v)} hitSlop={8}>
        <Text style={[styles.toggle, { color: accent }]}>
          {open ? hideLabel : revealLabel}
        </Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: "row",
    flexWrap: "wrap",
    alignItems: "center",
    gap: 6,
    marginTop: 4,
  },
  label: { color: "#333", fontWeight: "600" },
  value: { color: "#111", flexShrink: 1 },
  toggle: { fontWeight: "700", fontSize: 13 },
});
