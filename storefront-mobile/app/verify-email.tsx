import { useState } from "react";
import { Text, Pressable, View } from "react-native";
import { useLocalSearchParams, useRouter } from "expo-router";
import {
  resendEmailVerification,
  verifyEmail,
} from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import {
  AuthScreenShell,
  authFieldStyles as styles,
} from "../src/components/auth/AuthScreenShell";
import { LabeledInput } from "../src/components/LabeledInput";
import { PrimaryButton } from "../src/components/ui";
import { useRtl } from "../src/lib/rtl";
import { toast } from "../src/lib/toast";

export default function VerifyEmailScreen() {
  const { t, token, contact, updateContactLocal, accent } = useApp();
  const router = useRouter();
  const params = useLocalSearchParams<{ email?: string }>();
  const { textAlign, writingDirection } = useRtl();
  const email = (params.email || contact?.email || "").toString();
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <AuthScreenShell
      title={t("auth.verifyTitle")}
      footer={
        <Text style={[styles.link, { textAlign, writingDirection }]}>
          {t("auth.haveAccount")}{" "}
          <Text
            style={[styles.linkAccent, { color: accent }]}
            onPress={() => router.replace("/login")}
          >
            {t("auth.goToLogin")}
          </Text>
        </Text>
      }
    >
      <LabeledInput
        label={t("auth.email")}
        value={email}
        editable={false}
      />
      <Text style={[styles.hint, { textAlign, writingDirection }]}>
        {t("auth.verifyHint")}
      </Text>
      <LabeledInput
        label={t("auth.verifyCode")}
        value={code}
        onChangeText={(v) => setCode(v.replace(/\D/g, "").slice(0, 6))}
        keyboardType="number-pad"
        maxLength={6}
        style={{ letterSpacing: 4 }}
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <PrimaryButton
        label={busy ? t("common.loading") : t("auth.verify")}
        disabled={busy || code.length !== 6}
        onPress={() => {
          setBusy(true);
          setError(null);
          void verifyEmail({ code, email: email || undefined }, token)
            .then(async ({ data }) => {
              if (data.contact) {
                await updateContactLocal(data.contact);
              }
              toast.success(t("auth.verified"));
              router.replace("/(tabs)/account");
            })
            .catch((e) =>
              setError(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
      <View style={{ height: 14 }} />
      <Pressable
        onPress={() => {
          void resendEmailVerification({ email: email || undefined }, token)
            .then(() => toast.success(t("auth.codeSent")))
            .catch((e) =>
              toast.error(
                e instanceof Error ? e.message : t("common.error"),
              ),
            );
        }}
      >
        <Text
          style={[
            styles.linkAccent,
            { color: accent, textAlign: "center", writingDirection },
          ]}
        >
          {t("auth.resendCode")}
        </Text>
      </Pressable>
    </AuthScreenShell>
  );
}
