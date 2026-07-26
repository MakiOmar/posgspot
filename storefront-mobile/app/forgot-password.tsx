import { useState } from "react";
import { Text, View } from "react-native";
import { Link, Stack } from "expo-router";
import { forgotPassword } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { LabeledInput } from "../src/components/LabeledInput";
import { PrimaryButton, Screen } from "../src/components/ui";
import { STOREFRONT_WEB_URL } from "../src/lib/config";

export default function ForgotPasswordScreen() {
  const { t } = useApp();
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  return (
    <Screen>
      <Stack.Screen options={{ title: t("auth.forgotPassword") }} />
      <Text style={{ color: "#666", marginBottom: 12, lineHeight: 20 }}>
        {t("auth.forgotHint")}
      </Text>
      <LabeledInput
        label={t("auth.email")}
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
      />
      {message ? (
        <Text style={{ marginBottom: 10, color: "#333" }}>{message}</Text>
      ) : null}
      <PrimaryButton
        label={busy ? t("common.loading") : t("auth.sendReset")}
        disabled={busy || !email.trim()}
        onPress={() => {
          setBusy(true);
          setMessage(null);
          void forgotPassword(email.trim())
            .then(({ data }) =>
              setMessage(
                (data as { message?: string }).message || t("auth.resetSent"),
              ),
            )
            .catch((e) =>
              setMessage(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
      <View style={{ height: 12 }} />
      <Text style={{ color: "#666", marginBottom: 12, lineHeight: 20 }}>
        {t("auth.resetOnWeb")}: {STOREFRONT_WEB_URL}
      </Text>
      <Link href="/login">{t("common.login")}</Link>
    </Screen>
  );
}
