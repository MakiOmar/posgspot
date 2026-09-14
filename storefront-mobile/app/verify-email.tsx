import { useEffect, useRef, useState } from "react";
import { Text, Pressable, View } from "react-native";
import { useLocalSearchParams, useRouter, type Href } from "expo-router";
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
  const params = useLocalSearchParams<{ email?: string; next?: string }>();
  const { textAlign, writingDirection } = useRtl();
  const email = (params.email || contact?.email || "").toString();
  const nextHref = (params.next || "/(tabs)/account").toString();
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [sending, setSending] = useState(false);
  const sentOnce = useRef(false);

  const sendCode = (showToast: boolean) => {
    if (!email && !token) {
      return;
    }
    setSending(true);
    void resendEmailVerification({ email: email || undefined }, token)
      .then(() => {
        if (showToast) {
          toast.success(t("auth.codeSent"));
        }
      })
      .catch((e) =>
        toast.error(e instanceof Error ? e.message : t("auth.codeSendFailed")),
      )
      .finally(() => setSending(false));
  };

  useEffect(() => {
    if (sentOnce.current) {
      return;
    }
    sentOnce.current = true;
    sendCode(true);
    // Send once when the screen opens so "Verify email" actually emails a code.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

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
        {sending ? t("auth.codeSending") : t("auth.verifyHint")}
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
              router.replace(nextHref as Href);
            })
            .catch((e) =>
              setError(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
      <View style={{ height: 14 }} />
      <Pressable disabled={sending} onPress={() => sendCode(true)}>
        <Text
          style={[
            styles.linkAccent,
            { color: accent, textAlign: "center", writingDirection },
          ]}
        >
          {sending ? t("auth.codeSending") : t("auth.resendCode")}
        </Text>
      </Pressable>
    </AuthScreenShell>
  );
}
