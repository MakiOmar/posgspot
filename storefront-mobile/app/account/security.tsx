import { useState } from "react";
import { StyleSheet, Switch, Text, View } from "react-native";
import { Redirect } from "expo-router";
import { changePassword } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { LabeledInput } from "../../src/components/LabeledInput";
import { FormScrollView, PrimaryButton, Screen } from "../../src/components/ui";
import { toast } from "../../src/lib/toast";
import { useRtl } from "../../src/lib/rtl";

export default function LoginSecurityScreen() {
  const {
    token,
    t,
    applySession,
    passkeyEnabled,
    passkeyHardware,
    enablePasskey,
    disablePasskey,
  } = useApp();
  const { textAlign, writingDirection } = useRtl();
  const [current, setCurrent] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);
  const [passkeyBusy, setPasskeyBusy] = useState(false);

  if (!token) {
    return <Redirect href="/login" />;
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <LabeledInput
          label={t("account.currentPassword")}
          value={current}
          onChangeText={setCurrent}
          secureTextEntry
        />
        <LabeledInput
          label={t("account.newPassword")}
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
        <PrimaryButton
          label={busy ? t("common.loading") : t("account.savePassword")}
          disabled={busy}
          onPress={() => {
            if (password !== confirm) {
              toast.error(t("auth.confirmPassword"));
              return;
            }
            setBusy(true);
            void changePassword(token, {
              current_password: current,
              password,
              password_confirmation: confirm,
            })
              .then(async ({ data }) => {
                await applySession({
                  token: data.token,
                  token_type: data.token_type,
                  contact: data.contact,
                });
                setCurrent("");
                setPassword("");
                setConfirm("");
                toast.success(t("account.passwordChanged"));
              })
              .catch((e) =>
                toast.error(
                  e instanceof Error ? e.message : t("common.error"),
                ),
              )
              .finally(() => setBusy(false));
          }}
        />

        <View style={styles.passkeyBlock}>
          <Text style={[styles.passkeyTitle, { textAlign, writingDirection }]}>
            {t("account.passkey")}
          </Text>
          <Text style={[styles.passkeyHint, { textAlign, writingDirection }]}>
            {t("account.passkeyHint")}
          </Text>
          <View style={styles.passkeyRow}>
            <Text style={{ flex: 1, textAlign, writingDirection }}>
              {passkeyEnabled ? t("account.passkeyOn") : t("account.passkeyOff")}
            </Text>
            <Switch
              value={passkeyEnabled}
              disabled={passkeyBusy || (!passkeyHardware && !passkeyEnabled)}
              onValueChange={(next) => {
                setPasskeyBusy(true);
                const action = next ? enablePasskey() : disablePasskey();
                void action
                  .catch((e) =>
                    toast.error(
                      e instanceof Error ? e.message : t("common.error"),
                    ),
                  )
                  .finally(() => setPasskeyBusy(false));
              }}
            />
          </View>
        </View>
      </FormScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  passkeyBlock: { marginTop: 28, gap: 8 },
  passkeyTitle: { fontSize: 16, fontWeight: "800", color: "#111" },
  passkeyHint: { color: "#888", lineHeight: 20 },
  passkeyRow: { flexDirection: "row", alignItems: "center", gap: 12, marginTop: 8 },
});
