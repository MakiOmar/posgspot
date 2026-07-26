import { StyleSheet, Text, View, type TextInputProps } from "react-native";
import { useRtl } from "../lib/rtl";
import { FormTextInput } from "./FormTextInput";

type Props = TextInputProps & {
  /** Visible label above the field (preferred). */
  label?: string;
  hint?: string;
};

/**
 * Form field with label and/or placeholder.
 * Always uses FormTextInput so typed text stays dark on white backgrounds.
 */
export function LabeledInput({ label, hint, style, placeholder, ...rest }: Props) {
  const { textAlign, writingDirection } = useRtl();
  return (
    <View style={styles.wrap}>
      {label ? (
        <Text style={[styles.label, { textAlign, writingDirection }]}>
          {label}
        </Text>
      ) : null}
      <FormTextInput
        style={style}
        placeholder={placeholder || label || undefined}
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
  wrap: { marginBottom: 12 },
  label: { fontWeight: "700", marginBottom: 6, color: "#222", fontSize: 14 },
  hint: { marginTop: 4, color: "#666", fontSize: 12 },
});
