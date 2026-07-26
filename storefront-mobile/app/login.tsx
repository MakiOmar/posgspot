import { useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import {
  AuthScreenShell,
  authFieldStyles as styles,
} from "../src/components/auth/AuthScreenShell";
import { LabeledInput } from "../src/components/LabeledInput";
import { PhoneInput } from "../src/components/PhoneInput";
import { PrimaryButton } from "../src/components/ui";
import { useRtl } from "../src/lib/rtl";

type LoginMethod = "email" | "phone";

export default function LoginScreen() {
  const { t, signIn, accent } = useApp();
  const router = useRouter();
  const { textAlign, writingDirection, row } = useRtl();
  const [method, setMethod] = useState<LoginMethod>("email");
  const [email, setEmail] = useState("");
  const [dialCode, setDialCode] = useState("+20");
  const [national, setNational] = useState("");
  const [fullPhone, setFullPhone] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const loginId =
    method === "email" ? email.trim() : fullPhone.trim() || `${dialCode}${national}`;

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
      <View style={[tabStyles.tabs, { flexDirection: row }]}>
        {(["email", "phone"] as const).map((key) => {
          const active = method === key;
          return (
            <Pressable
              key={key}
              style={[
                tabStyles.tab,
                active && { borderBottomColor: accent, borderBottomWidth: 2 },
              ]}
              onPress={() => setMethod(key)}
            >
              <Text
                style={[
                  tabStyles.tabText,
                  active && { color: accent, fontWeight: "800" },
                ]}
              >
                {key === "email" ? t("auth.email") : t("auth.phone")}
              </Text>
            </Pressable>
          );
        })}
      </View>

      {method === "email" ? (
        <LabeledInput
          label={t("auth.email")}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
        />
      ) : (
        <PhoneInput
          label={t("auth.phone")}
          dialCode={dialCode}
          nationalNumber={national}
          onChange={({ dialCode: d, nationalNumber: n, fullPhone: f }) => {
            setDialCode(d);
            setNational(n);
            setFullPhone(f);
          }}
        />
      )}

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
          if (method === "phone" && !national.trim()) {
            setError(t("checkout.requiredContact"));
            return;
          }
          if (!loginId || !password) {
            setError(t("checkout.requiredContact"));
            return;
          }
          setBusy(true);
          setError(null);
          void signIn(loginId, password)
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

const tabStyles = StyleSheet.create({
  tabs: {
    marginBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: "#e5e5e5",
  },
  tab: {
    flex: 1,
    paddingVertical: 10,
    alignItems: "center",
  },
  tabText: { color: "#666", fontWeight: "600" },
});
