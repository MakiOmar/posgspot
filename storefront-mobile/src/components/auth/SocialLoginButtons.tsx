import { useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import {
  loginWithSocialToken,
  type SocialProvider,
} from "../../lib/api";
import { promptSocialAuth } from "../../lib/social-auth";
import { useApp } from "../../contexts/AppContext";
import { PrimaryButton } from "../ui";
import { toast } from "../../lib/toast";
import { useRtl } from "../../lib/rtl";

type Props = {
  intent?: "login" | "link";
  onSuccess?: () => void;
};

/**
 * Google / Facebook buttons when settings.social_login.* is enabled.
 */
export function SocialLoginButtons({ intent = "login", onSuccess }: Props) {
  const { t, settings, applySession, token } = useApp();
  const { textAlign, writingDirection } = useRtl();
  const [busy, setBusy] = useState<SocialProvider | null>(null);

  const google = !!settings?.social_login?.google_enabled;
  const facebook = !!settings?.social_login?.facebook_enabled;
  if (!google && !facebook) {
    return null;
  }

  const run = (provider: SocialProvider) => {
    setBusy(provider);
    void (async () => {
      try {
        const tokens = await promptSocialAuth(provider);
        const { data } = await loginWithSocialToken(
          provider,
          {
            ...tokens,
            intent,
          },
          intent === "link" ? token : null,
        );
        await applySession({
          token: data.token,
          token_type: data.token_type,
          contact: data.contact,
        });
        toast.success(
          intent === "link"
            ? t("auth.socialConnected")
            : t("auth.socialSuccess"),
        );
        onSuccess?.();
      } catch (e) {
        const msg = e instanceof Error ? e.message : t("auth.socialFailed");
        if (!/cancel/i.test(msg)) {
          toast.error(msg);
        }
      } finally {
        setBusy(null);
      }
    })();
  };

  return (
    <View style={styles.wrap}>
      <Text style={[styles.divider, { textAlign, writingDirection }]}>
        {t("auth.orContinueWith")}
      </Text>
      {google ? (
        <PrimaryButton
          label={
            busy === "google"
              ? t("common.loading")
              : intent === "link"
                ? t("auth.connectGoogle")
                : t("auth.continueGoogle")
          }
          disabled={!!busy}
          onPress={() => run("google")}
          style={styles.secondaryBtn}
        />
      ) : null}
      {facebook ? (
        <PrimaryButton
          label={
            busy === "facebook"
              ? t("common.loading")
              : intent === "link"
                ? t("auth.connectFacebook")
                : t("auth.continueFacebook")
          }
          disabled={!!busy}
          onPress={() => run("facebook")}
          style={styles.secondaryBtn}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { gap: 10, marginTop: 16, marginBottom: 8 },
  divider: { color: "#888", fontSize: 13, marginBottom: 4, textAlign: "center" },
  secondaryBtn: { backgroundColor: "#222" },
});
