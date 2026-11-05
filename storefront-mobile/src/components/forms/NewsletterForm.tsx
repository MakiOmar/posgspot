import { useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import { ApiError, subscribeNewsletter } from "../../lib/api";
import { useRtl } from "../../lib/rtl";
import { toast } from "../../lib/toast";
import { FormTextInput } from "../FormTextInput";
import { PrimaryButton } from "../ui";
import { TurnstileWidget, useTurnstile } from "./Turnstile";

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Email signup (same rules as the website footer); renders nothing unless `settings.newsletter.enabled`. */
export function NewsletterForm() {
  const { t, settings, locale } = useApp();
  const { textAlign, writingDirection } = useRtl();
  const turnstile = useTurnstile();
  const [email, setEmail] = useState("");
  const [busy, setBusy] = useState(false);
  /** Turnstile loads only after the visitor engages the field (like the website). */
  const [engaged, setEngaged] = useState(false);

  if (!settings?.newsletter?.enabled) {
    return null;
  }

  const submit = async () => {
    const value = email.trim();
    if (!EMAIL_RE.test(value)) {
      toast.error(t("newsletter.invalidEmail"));
      return;
    }
    if (!turnstile.ensure()) {
      setEngaged(true);
      return;
    }
    setBusy(true);
    try {
      const { data } = await subscribeNewsletter({ email: value, ...turnstile.payload() }, locale);
      toast.success(data.message || t("newsletter.subscribed"));
      setEmail("");
    } catch (e) {
      toast.error(e instanceof ApiError && e.message ? e.message : t("newsletter.failed"));
    } finally {
      turnstile.reset();
      setBusy(false);
    }
  };

  return (
    <View style={styles.wrap}>
      <Text style={[styles.title, { textAlign, writingDirection }]}>{t("newsletter.title")}</Text>
      <Text style={[styles.blurb, { textAlign, writingDirection }]}>{t("newsletter.blurb")}</Text>
      <FormTextInput
        value={email}
        onChangeText={setEmail}
        onFocus={() => setEngaged(true)}
        placeholder={t("newsletter.placeholder")}
        accessibilityLabel={t("newsletter.emailLabel")}
        autoCapitalize="none"
        autoComplete="email"
        keyboardType="email-address"
        editable={!busy}
      />
      {engaged ? <TurnstileWidget turnstile={turnstile} /> : null}
      <PrimaryButton
        label={busy ? t("newsletter.submitting") : t("newsletter.subscribe")}
        disabled={busy}
        onPress={() => void submit()}
        style={styles.button}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    backgroundColor: "#fff",
    borderRadius: 14,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e5e5e5",
    padding: 16,
    gap: 8,
    marginVertical: 12,
  },
  title: { fontSize: 17, fontWeight: "800", color: "#111" },
  blurb: { color: "#666", lineHeight: 20 },
  button: { marginTop: 4 },
});
