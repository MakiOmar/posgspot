import { useState } from "react";
import { View } from "react-native";
import { Redirect, Stack, useRouter } from "expo-router";
import { changePassword } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { HeaderCartButton } from "../../src/components/account/HeaderCartButton";
import { LabeledInput } from "../../src/components/LabeledInput";
import { PrimaryButton, Screen } from "../../src/components/ui";
import { toast } from "../../src/lib/toast";

export default function ChangePasswordScreen() {
  const { token, t, applySession } = useApp();
  const router = useRouter();
  const [current, setCurrent] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [busy, setBusy] = useState(false);

  if (!token) {
    return <Redirect href="/login" />;
  }

  return (
    <Screen>
      <Stack.Screen
        options={{
          title: t("account.changePassword"),
          headerRight: () => <HeaderCartButton />,
        }}
      />
      <View>
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
                toast.success(t("account.passwordChanged"));
                router.back();
              })
              .catch((e) =>
                toast.error(
                  e instanceof Error ? e.message : t("common.error"),
                ),
              )
              .finally(() => setBusy(false));
          }}
        />
      </View>
    </Screen>
  );
}
