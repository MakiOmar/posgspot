import { useState } from "react";
import { Text } from "react-native";
import { submitContact } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { LabeledInput } from "../src/components/LabeledInput";
import { FormScrollView, PrimaryButton, Screen } from "../src/components/ui";

export default function ContactScreen() {
  const { t } = useApp();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [mobile, setMobile] = useState("");
  const [message, setMessage] = useState("");
  const [status, setStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <LabeledInput
          label={t("contact.name")}
          value={name}
          onChangeText={setName}
        />
        <LabeledInput
          label={t("auth.email")}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
        />
        <LabeledInput
          label={t("checkout.mobile")}
          value={mobile}
          onChangeText={setMobile}
          keyboardType="phone-pad"
        />
        <LabeledInput
          label={t("contact.message")}
          value={message}
          onChangeText={setMessage}
          multiline
          style={{ height: 120, textAlignVertical: "top" }}
        />
        {status ? (
          <Text style={{ marginBottom: 8, color: "#333" }}>{status}</Text>
        ) : null}
        <PrimaryButton
          label={busy ? t("common.loading") : t("contact.send")}
          disabled={busy}
          onPress={() => {
            setBusy(true);
            void submitContact({ name, email, mobile, message })
              .then(() => setStatus(t("contact.sent")))
              .catch((e) =>
                setStatus(e instanceof Error ? e.message : t("common.error")),
              )
              .finally(() => setBusy(false));
          }}
        />
      </FormScrollView>
    </Screen>
  );
}
