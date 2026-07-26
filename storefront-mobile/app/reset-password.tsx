import { useState } from "react";
import { Text } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { resetPassword } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { LabeledInput } from "../src/components/LabeledInput";
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
      <LabeledInput
        label={t("auth.email")}
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
      />
      <LabeledInput
        label={t("auth.resetToken")}
        value={token}
        onChangeText={setToken}
        autoCapitalize="none"
      />
      <LabeledInput
        label={t("auth.password")}
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      <LabeledInput
        label={t("auth.confirmPassword")}
        value={confirm}
        onChangeText={setConfirm}
        secureTextEntry
      />
      {message ? (
        <Text style={{ marginBottom: 10, color: "#333" }}>{message}</Text>
      ) : null}
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
