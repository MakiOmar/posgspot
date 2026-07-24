import { useState } from "react";
import { StyleSheet, Text, TextInput } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { resetPassword } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../src/components/ui";

export default function ResetPasswordScreen() {
  const { t } = useApp();
  const router = useRouter();
  const params = useLocalSearchParams<{ email?: string; token?: string }>();
  const [email, setEmail] = useState(params.email || "");
  const [token, setToken] = useState(params.token || "");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  return (
    <Screen>
      <Stack.Screen options={{ title: t("auth.resetPassword") }} />
      <TextInput
        style={styles.input}
        placeholder="Email"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
      />
      <TextInput
        style={styles.input}
        placeholder="Token"
        value={token}
        onChangeText={setToken}
        autoCapitalize="none"
      />
      <TextInput
        style={styles.input}
        placeholder={t("auth.password")}
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      <TextInput
        style={styles.input}
        placeholder={t("auth.confirmPassword")}
        value={confirm}
        onChangeText={setConfirm}
        secureTextEntry
      />
      {message ? <Text style={styles.msg}>{message}</Text> : null}
      <PrimaryButton
        label={busy ? t("common.loading") : t("auth.resetPassword")}
        disabled={busy}
        onPress={() => {
          setBusy(true);
          setMessage(null);
          void resetPassword({
            email: email.trim(),
            token: token.trim(),
            password,
            password_confirmation: confirm,
          })
            .then(() => {
              setMessage(t("auth.passwordUpdated"));
              router.replace("/login");
            })
            .catch((e) =>
              setMessage(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 10,
  },
  msg: { marginBottom: 10, color: "#333" },
});
