import { useState } from "react";
import { StyleSheet, Text, TextInput, View } from "react-native";
import { Link, Stack } from "expo-router";
import { forgotPassword } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
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
      <Text style={styles.hint}>{t("auth.forgotHint")}</Text>
      <TextInput
        style={styles.input}
        placeholder="Email"
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
      />
      {message ? <Text style={styles.msg}>{message}</Text> : null}
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
      <Text style={styles.hint}>
        {t("auth.resetOnWeb")}: {STOREFRONT_WEB_URL}
      </Text>
      <Link href="/login">{t("common.login")}</Link>
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
  hint: { color: "#666", marginBottom: 12, lineHeight: 20 },
  msg: { marginBottom: 10, color: "#333" },
});
