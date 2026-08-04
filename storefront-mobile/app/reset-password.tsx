import { useRef, useState } from "react";
import { Text } from "react-native";
import { Stack, useLocalSearchParams, useRouter } from "expo-router";
import { resetPassword } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { LabeledInput } from "../src/components/LabeledInput";
import { FormScrollView, PrimaryButton, Screen } from "../src/components/ui";

export default function ResetPasswordScreen() {
  const { t } = useApp();
  const router = useRouter();
  const params = useLocalSearchParams<{ email?: string; token?: string }>();
  // Keep token in memory only — never show it in a form field.
  const tokenRef = useRef((params.token || "").toString());
  const [email, setEmail] = useState((params.email || "").toString());
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <Stack.Screen options={{ title: t("auth.resetPassword") }} />
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <LabeledInput
          label={t("auth.email")}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
        />
        <LabeledInput
          label={t("auth.password")}
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          textContentType="newPassword"
          autoComplete="password-new"
        />
        <LabeledInput
          label={t("auth.confirmPassword")}
          value={confirm}
          onChangeText={setConfirm}
          secureTextEntry
          textContentType="newPassword"
          autoComplete="password-new"
        />
        {message ? (
          <Text style={{ marginBottom: 10, color: "#333" }}>{message}</Text>
        ) : null}
        <PrimaryButton
          label={busy ? t("common.loading") : t("auth.resetPassword")}
          disabled={busy || !tokenRef.current}
          onPress={() => {
            const token = tokenRef.current.trim();
            if (!token) {
              setMessage(t("common.error"));
              return;
            }
            setBusy(true);
            setMessage(null);
            void resetPassword({
              email: email.trim(),
              token,
              password,
              password_confirmation: confirm,
            })
              .then(() => {
                tokenRef.current = "";
                setMessage(t("auth.passwordUpdated"));
                router.replace("/login");
              })
              .catch((e) =>
                setMessage(e instanceof Error ? e.message : t("common.error")),
              )
              .finally(() => setBusy(false));
          }}
        />
      </FormScrollView>
    </Screen>
  );
}
