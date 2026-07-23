import { useState } from "react";
import { StyleSheet, Text, TextInput } from "react-native";
import { submitContact } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../src/components/ui";

export default function ContactScreen() {
  const { t } = useApp();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [mobile, setMobile] = useState("");
  const [message, setMessage] = useState("");
  const [status, setStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <Screen>
      <TextInput style={styles.input} placeholder="Name" value={name} onChangeText={setName} />
      <TextInput style={styles.input} placeholder="Email" value={email} onChangeText={setEmail} autoCapitalize="none" />
      <TextInput style={styles.input} placeholder="Mobile" value={mobile} onChangeText={setMobile} />
      <TextInput
        style={[styles.input, { height: 120, textAlignVertical: "top" }]}
        placeholder="Message"
        value={message}
        onChangeText={setMessage}
        multiline
      />
      {status ? <Text style={{ marginBottom: 8 }}>{status}</Text> : null}
      <PrimaryButton
        label={busy ? t("common.loading") : "Send"}
        disabled={busy}
        onPress={() => {
          setBusy(true);
          void submitContact({ name, email, mobile, message })
            .then(() => setStatus("Message sent"))
            .catch((e) =>
              setStatus(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 10,
  },
});
