import { useState } from "react";
import { Pressable, Text, View } from "react-native";
import { useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import {
  AuthScreenShell,
  authFieldStyles as styles,
} from "../src/components/auth/AuthScreenShell";
import { LabeledInput } from "../src/components/LabeledInput";
import { PrimaryButton } from "../src/components/ui";
import { useRtl } from "../src/lib/rtl";

export default function LoginScreen() {
  const { t, signIn, accent } = useApp();
  const router = useRouter();
  const { textAlign, writingDirection } = useRtl();
  const [loginId, setLoginId] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <AuthScreenShell
      title={t("auth.signIn")}
      footer={
        <View style={{ alignItems: "center", gap: 12, width: "100%" }}>
          <Text style={[styles.link, { textAlign, writingDirection }]}>
            {t("auth.noAccount")}{" "}
            <Text
              style={[styles.linkAccent, { color: accent }]}
              onPress={() => router.push("/register")}
            >
              {t("auth.registerNow")}
            </Text>
          </Text>
          <PrimaryButton
            label={t("auth.createAccount")}
            onPress={() => router.push("/register")}
            style={{ alignSelf: "stretch" }}
          />
        </View>
      }
    >
      <LabeledInput
        label={t("auth.emailOrMobile")}
        value={loginId}
        onChangeText={setLoginId}
        autoCapitalize="none"
        keyboardType="email-address"
      />
      <LabeledInput
        label={t("auth.password")}
        value={password}
        onChangeText={setPassword}
        secureTextEntry
      />
      <Pressable
        style={styles.linkRow}
        onPress={() => router.push("/forgot-password")}
      >
        <Text
          style={[
            styles.link,
            { textAlign: "right", writingDirection, color: accent },
          ]}
        >
          {t("auth.forgotPassword")}
        </Text>
      </Pressable>
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <PrimaryButton
        label={busy ? t("common.loading") : t("auth.login")}
        disabled={busy}
        onPress={() => {
          setBusy(true);
          setError(null);
          void signIn(loginId.trim(), password)
            .then(() => {
              router.replace("/(tabs)/account");
            })
            .catch((e) =>
              setError(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
    </AuthScreenShell>
  );
}
