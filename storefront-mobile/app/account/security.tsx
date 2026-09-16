import { useCallback, useEffect, useState } from "react";
import { Pressable, StyleSheet, Switch, Text, View } from "react-native";
import { Redirect } from "expo-router";
import {
  changePassword,
  disconnectSocialProvider,
  fetchSocialIdentities,
  loginWithSocialToken,
  type SocialProvider,
} from "../../src/lib/api";
import { promptSocialAuth } from "../../src/lib/social-auth";
import { useApp } from "../../src/contexts/AppContext";
import { LabeledInput } from "../../src/components/LabeledInput";
import { FormScrollView, PrimaryButton, Screen } from "../../src/components/ui";
import { toast } from "../../src/lib/toast";
import { useRtl } from "../../src/lib/rtl";

type IdentityRow = {
  provider: string;
  email: string | null;
  connected: boolean;
};

export default function LoginSecurityScreen() {
  const {
    token,
    t,
    applySession,
    settings,
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
  const [identities, setIdentities] = useState<IdentityRow[]>([]);
  const [socialBusy, setSocialBusy] = useState<SocialProvider | null>(null);

  const googleEnabled = !!settings?.social_login?.google_enabled;
  const facebookEnabled = !!settings?.social_login?.facebook_enabled;
  const socialEnabled = googleEnabled || facebookEnabled;

  const loadIdentities = useCallback(() => {
    if (!token || !socialEnabled) {
      return;
    }
    void fetchSocialIdentities(token)
      .then(({ data }) => setIdentities(data.identities || []))
      .catch(() => setIdentities([]));
  }, [token, socialEnabled]);

  useEffect(() => {
    loadIdentities();
  }, [loadIdentities]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  const connected = new Set(
    identities.filter((i) => i.connected).map((i) => i.provider),
  );

  const connect = (provider: SocialProvider) => {
    setSocialBusy(provider);
    void (async () => {
      try {
        const tokens = await promptSocialAuth(provider);
        const { data } = await loginWithSocialToken(
          provider,
          { ...tokens, intent: "link" },
          token,
        );
        await applySession({
          token: data.token,
          token_type: data.token_type,
          contact: data.contact,
        });
        toast.success(t("auth.socialConnected"));
        loadIdentities();
      } catch (e) {
        const msg = e instanceof Error ? e.message : t("auth.socialFailed");
        if (!/cancel/i.test(msg)) {
          toast.error(msg);
        }
      } finally {
        setSocialBusy(null);
      }
    })();
  };

  const disconnect = (provider: SocialProvider) => {
    setSocialBusy(provider);
    void disconnectSocialProvider(token, provider)
      .then(({ data }) => {
        setIdentities(data.identities || []);
        toast.success(t("auth.socialDisconnected"));
      })
      .catch((e) =>
        toast.error(
          e instanceof Error ? e.message : t("auth.socialDisconnectFailed"),
        ),
      )
      .finally(() => setSocialBusy(null));
  };

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

        {socialEnabled ? (
          <View style={styles.socialBlock}>
            <Text style={[styles.socialTitle, { textAlign, writingDirection }]}>
              {t("account.socialAccounts")}
            </Text>
            <Text style={[styles.socialHint, { textAlign, writingDirection }]}>
              {t("account.socialAccountsHint")}
            </Text>
            {googleEnabled ? (
              <View style={styles.socialRow}>
                <Text style={{ flex: 1, textAlign, writingDirection }}>
                  Google
                  {connected.has("google")
                    ? ` · ${t("auth.connected")}`
                    : ""}
                </Text>
                <Pressable
                  disabled={socialBusy === "google"}
                  onPress={() =>
                    connected.has("google")
                      ? disconnect("google")
                      : connect("google")
                  }
                >
                  <Text style={styles.socialAction}>
                    {socialBusy === "google"
                      ? t("common.loading")
                      : connected.has("google")
                        ? t("auth.disconnect")
                        : t("auth.connectGoogle")}
                  </Text>
                </Pressable>
              </View>
            ) : null}
            {facebookEnabled ? (
              <View style={styles.socialRow}>
                <Text style={{ flex: 1, textAlign, writingDirection }}>
                  Facebook
                  {connected.has("facebook")
                    ? ` · ${t("auth.connected")}`
                    : ""}
                </Text>
                <Pressable
                  disabled={socialBusy === "facebook"}
                  onPress={() =>
                    connected.has("facebook")
                      ? disconnect("facebook")
                      : connect("facebook")
                  }
                >
                  <Text style={styles.socialAction}>
                    {socialBusy === "facebook"
                      ? t("common.loading")
                      : connected.has("facebook")
                        ? t("auth.disconnect")
                        : t("auth.connectFacebook")}
                  </Text>
                </Pressable>
              </View>
            ) : null}
          </View>
        ) : null}

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
  passkeyRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginTop: 8,
  },
  socialBlock: { marginTop: 28, gap: 8 },
  socialTitle: { fontSize: 16, fontWeight: "800", color: "#111" },
  socialHint: { color: "#888", lineHeight: 20 },
  socialRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 12,
    marginTop: 8,
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#ddd",
  },
  socialAction: { color: "#0B57D0", fontWeight: "700" },
});
