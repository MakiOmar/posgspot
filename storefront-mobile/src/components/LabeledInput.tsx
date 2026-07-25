import { StyleSheet, Text, TextInput, View, type TextInputProps } from "react-native";
import { useRtl } from "../lib/rtl";

type Props = TextInputProps & {
  label: string;
  hint?: string;
};

/** Labeled text field used on cart/checkout forms. */
export function LabeledInput({ label, hint, style, ...rest }: Props) {
  const { textAlign, writingDirection } = useRtl();
  return (
    <View style={styles.wrap}>
      {label ? (
        <Text style={[styles.label, { textAlign, writingDirection }]}>
          {label}
        </Text>
      ) : null}
      <TextInput
        style={[styles.input, { textAlign, writingDirection }, style]}
        placeholderTextColor="#999"
        {...rest}
      />
      {hint ? (
        <Text style={[styles.hint, { textAlign, writingDirection }]}>
          {hint}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 10 },
  label: { fontWeight: "700", marginBottom: 6, color: "#222" },
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    color: "#111",
  },
  hint: { marginTop: 4, color: "#666", fontSize: 12 },
});
