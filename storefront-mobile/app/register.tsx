import { useState } from "react";
import { StyleSheet, Text, TextInput } from "react-native";
import { useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../src/components/ui";

export default function RegisterScreen() {
  const { t, signUp } = useApp();
  const router = useRouter();
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [mobile, setMobile] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  return (
    <Screen>
      <TextInput style={styles.input} placeholder="First name" value={firstName} onChangeText={setFirstName} />
      <TextInput style={styles.input} placeholder="Last name" value={lastName} onChangeText={setLastName} />
      <TextInput style={styles.input} placeholder="Email" value={email} onChangeText={setEmail} autoCapitalize="none" />
      <TextInput style={styles.input} placeholder="Mobile" value={mobile} onChangeText={setMobile} keyboardType="phone-pad" />
      <TextInput style={styles.input} placeholder={t("auth.password")} value={password} onChangeText={setPassword} secureTextEntry />
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <PrimaryButton
        label={busy ? t("common.loading") : t("common.register")}
        disabled={busy}
        onPress={() => {
          setBusy(true);
          setError(null);
          void signUp({
            first_name: firstName,
            last_name: lastName,
            email,
            mobile,
            password,
            password_confirmation: password,
          })
            .then(() => router.replace("/(tabs)/account"))
            .catch((e) =>
              setError(e instanceof Error ? e.message : t("common.error")),
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
  error: { color: "#B00020", marginBottom: 8 },
});
