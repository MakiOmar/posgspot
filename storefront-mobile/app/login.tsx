import { useState } from "react";
import { StyleSheet, Text, TextInput, View } from "react-native";
import { Link, useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../src/components/ui";

export default function LoginScreen() {
  const { t, signIn } = useApp();
  const router = useRouter();
  const [loginId, setLoginId] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <Screen>
      <TextInput
        style={styles.input}
        placeholder={t("auth.emailOrMobile")}
        value={loginId}
        onChangeText={setLoginId}
        autoCapitalize="none"
      />
      <TextInput
        style={styles.input}
        placeholder={t("auth.password")}
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <PrimaryButton
        label={busy ? t("common.loading") : t("common.login")}
        disabled={busy}
        onPress={() => {
          setBusy(true);
          setError(null);
          void signIn(loginId.trim(), password)
            .then(() => router.replace("/(tabs)/account"))
            .catch((e) =>
              setError(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
      <View style={{ height: 12 }} />
      <Link href="/register">{t("common.register")}</Link>
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
  error: { color: "#B00020", marginBottom: 8 },
});
