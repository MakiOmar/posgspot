import { useCallback, useEffect, useState } from "react";
import {
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { Redirect, Stack } from "expo-router";
import { fetchProfile, updateProfile } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";

export default function ProfileScreen() {
  const { token, t } = useApp();
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [mobile, setMobile] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const { data } = await fetchProfile(token);
      const c = data as {
        first_name?: string;
        last_name?: string;
        email?: string;
        mobile?: string;
        name?: string;
      };
      setFirstName(c.first_name || "");
      setLastName(c.last_name || "");
      setEmail(c.email || "");
      setMobile(c.mobile || "");
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [token, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  if (loading) {
    return (
      <Screen>
        <Stack.Screen options={{ title: t("account.profile") }} />
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <Stack.Screen options={{ title: t("account.profile") }} />
      <ScrollView contentContainerStyle={styles.pad}>
        {error ? <ErrorBlock message={error} onRetry={() => void load()} /> : null}
        <TextInput style={styles.input} placeholder="First name" value={firstName} onChangeText={setFirstName} />
        <TextInput style={styles.input} placeholder="Last name" value={lastName} onChangeText={setLastName} />
        <TextInput style={styles.input} placeholder="Email" value={email} onChangeText={setEmail} autoCapitalize="none" />
        <TextInput style={styles.input} placeholder="Mobile" value={mobile} onChangeText={setMobile} keyboardType="phone-pad" />
        {message ? <Text style={styles.msg}>{message}</Text> : null}
        <PrimaryButton
          label={busy ? t("common.loading") : t("account.saveProfile")}
          disabled={busy}
          onPress={() => {
            setBusy(true);
            setMessage(null);
            void updateProfile(token, {
              first_name: firstName.trim(),
              last_name: lastName.trim(),
              email: email.trim(),
              mobile: mobile.trim(),
            })
              .then(() => setMessage(t("account.profileSaved")))
              .catch((e) =>
                setMessage(e instanceof Error ? e.message : t("common.error")),
              )
              .finally(() => setBusy(false));
          }}
        />
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16 },
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 10,
  },
  msg: { marginBottom: 10, color: "#333" },
});
