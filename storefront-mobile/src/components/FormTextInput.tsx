import {
  StyleSheet,
  TextInput,
  type TextInputProps,
  type StyleProp,
  type TextStyle,
} from "react-native";
import { useRtl } from "../lib/rtl";

/**
 * Shared text input with forced dark text on light fields.
 * Android dark-mode otherwise paints white text on white backgrounds.
 */
export function FormTextInput({
  style,
  placeholderTextColor = "#888",
  ...rest
}: TextInputProps) {
  const { textAlign, writingDirection } = useRtl();
  return (
    <TextInput
      {...rest}
      placeholderTextColor={placeholderTextColor}
      underlineColorAndroid="transparent"
      selectionColor="#666"
      style={[
        styles.input,
        { textAlign, writingDirection },
        style as StyleProp<TextStyle>,
        // Force visible text last so callers cannot accidentally wipe color.
        styles.forcedText,
      ]}
    />
  );
}

const styles = StyleSheet.create({
  input: {
    backgroundColor: "#fff",
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 14,
    paddingVertical: 14,
    fontSize: 16,
  },
  forcedText: {
    color: "#111",
  },
});
