import { useState } from "react";
import { Text } from "react-native";
import { useLocalSearchParams, useRouter, type Href } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import {
  AuthScreenShell,
  authFieldStyles as styles,
} from "../src/components/auth/AuthScreenShell";
import { LabeledInput } from "../src/components/LabeledInput";
import { PhoneInput } from "../src/components/PhoneInput";
import { PrimaryButton } from "../src/components/ui";
import { SocialLoginButtons } from "../src/components/auth/SocialLoginButtons";
import { useRtl } from "../src/lib/rtl";

export default function RegisterScreen() {
  const { t, signUp, accent } = useApp();
  const router = useRouter();
  const params = useLocalSearchParams<{ next?: string }>();
  const nextPath =
    typeof params.next === "string" && params.next.startsWith("/")
      ? params.next
      : "/(tabs)/account";
  const { textAlign, writingDirection } = useRtl();
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [dialCode, setDialCode] = useState("+20");
  const [national, setNational] = useState("");
  const [fullPhone, setFullPhone] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const loginHref = {
    pathname: "/login" as const,
    params: nextPath !== "/(tabs)/account" ? { next: nextPath } : undefined,
  };

  return (
    <AuthScreenShell
      title={t("common.register")}
      footer={
        <Text style={[styles.link, { textAlign, writingDirection }]}>
          {t("auth.haveAccount")}{" "}
          <Text
            style={[styles.linkAccent, { color: accent }]}
            onPress={() => router.push(loginHref as Href)}
          >
            {t("auth.goToLogin")}
          </Text>
        </Text>
      }
    >
      <LabeledInput
        label={t("auth.firstName")}
        value={firstName}
        onChangeText={setFirstName}
      />
      <LabeledInput
        label={t("auth.lastName")}
        value={lastName}
        onChangeText={setLastName}
      />
      <LabeledInput
        label={t("auth.email")}
        value={email}
        onChangeText={setEmail}
        autoCapitalize="none"
        keyboardType="email-address"
      />
      <PhoneInput
        label={t("auth.mobileOptional")}
        dialCode={dialCode}
        nationalNumber={national}
        onChange={({ dialCode: d, nationalNumber: n, fullPhone: f }) => {
          setDialCode(d);
          setNational(n);
          setFullPhone(f);
        }}
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
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <PrimaryButton
        label={busy ? t("common.loading") : t("auth.createAccount")}
        disabled={busy}
        onPress={() => {
          if (!email.trim() || !password) {
            setError(t("checkout.requiredContact"));
            return;
          }
          if (password !== confirm) {
            setError(t("auth.confirmPassword"));
            return;
          }
          setBusy(true);
          setError(null);
          const body: Record<string, unknown> = {
            first_name: firstName.trim() || "Customer",
            last_name: lastName.trim(),
            email: email.trim(),
            password,
            password_confirmation: confirm,
          };
          if (fullPhone || national) {
            body.mobile = fullPhone || `${dialCode}${national}`;
            body.dial_code = dialCode;
          }
          void signUp(body)
            .then(() =>
              router.replace({
                pathname: "/verify-email",
                params: { email: email.trim(), next: nextPath },
              } as unknown as Href),
            )
            .catch((e) =>
              setError(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
      <SocialLoginButtons
        intent="login"
        onSuccess={() => router.replace(nextPath as never)}
      />
    </AuthScreenShell>
  );
}
