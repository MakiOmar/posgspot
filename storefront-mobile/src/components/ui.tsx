import React from "react";
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { KeyboardAvoidingView } from "react-native-keyboard-controller";
import { useApp } from "../contexts/AppContext";
import { useRtl } from "../lib/rtl";

export { ProductCard } from "./catalog/ProductCard";
export { FormScrollView } from "./FormScrollView";

export function Screen({
  children,
  padded = true,
  /** Lift content above the soft keyboard (default on). */
  avoidKeyboard = true,
}: {
  children: React.ReactNode;
  padded?: boolean;
  avoidKeyboard?: boolean;
}) {
  const { isRtl } = useRtl();
  const body = (
    <View
      style={[
        styles.screen,
        padded && styles.padded,
        { direction: isRtl ? "rtl" : "ltr" },
      ]}
    >
      {children}
    </View>
  );

  if (!avoidKeyboard) {
    return body;
  }

  return (
    <KeyboardAvoidingView style={styles.flex} behavior="padding">
      {body}
    </KeyboardAvoidingView>
  );
}

export function LoadingBlock() {
  const { t, accent } = useApp();
  return (
    <View style={styles.center}>
      <ActivityIndicator color={accent} />
      <Text style={styles.muted}>{t("common.loading")}</Text>
    </View>
  );
}

export function ErrorBlock({
  message,
  onRetry,
}: {
  message?: string;
  onRetry?: () => void;
}) {
  const { t, accent } = useApp();
  return (
    <View style={styles.center}>
      <Text style={styles.error}>{message || t("common.error")}</Text>
      {onRetry ? (
        <Pressable
          style={[styles.button, { backgroundColor: accent }]}
          onPress={onRetry}
        >
          <Text style={styles.buttonText}>{t("common.retry")}</Text>
        </Pressable>
      ) : null}
    </View>
  );
}

export function PrimaryButton({
  label,
  onPress,
  disabled,
  style,
  ...rest
}: {
  label: string;
  onPress?: () => void;
  disabled?: boolean;
  style?: object;
  [key: string]: unknown;
}) {
  const { accent } = useApp();
  return (
    <Pressable
      {...rest}
      style={StyleSheet.flatten([
        styles.button,
        { backgroundColor: accent, opacity: disabled ? 0.5 : 1 },
        style,
      ])}
      disabled={disabled}
      onPress={onPress}
    >
      <Text style={styles.buttonText}>{label}</Text>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  screen: { flex: 1, backgroundColor: "#F7F7F5" },
  padded: { padding: 16 },
  center: { flex: 1, alignItems: "center", justifyContent: "center", gap: 12 },
  muted: { color: "#666" },
  error: { color: "#B00020", textAlign: "center" },
  button: {
    paddingVertical: 14,
    paddingHorizontal: 18,
    borderRadius: 10,
    alignItems: "center",
  },
  buttonText: { color: "#fff", fontWeight: "700" },
});
