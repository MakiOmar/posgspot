import { useEffect, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { Link, useRouter } from "expo-router";
import { useApp } from "../src/contexts/AppContext";
import { FormScrollView, PrimaryButton, Screen } from "../src/components/ui";
import {
  fetchRequestProductMeta,
  submitRequestProduct,
} from "../src/lib/api";
import { LabeledInput } from "../src/components/LabeledInput";
import { SelectField } from "../src/components/SelectField";
import type { RequestProductMeta } from "../src/lib/types";

export default function RequestProductScreen() {
  const { t, settings, token, contact, accent } = useApp();
  const router = useRouter();
  const enabled = settings?.request_product?.enabled === true;

  const [meta, setMeta] = useState<RequestProductMeta | null>(null);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [productName, setProductName] = useState("");
  const [platform, setPlatform] = useState("");
  const [notes, setNotes] = useState("");
  const [status, setStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!enabled) return;
    void fetchRequestProductMeta()
      .then(({ data }) => setMeta(data))
      .catch(() => setMeta(null));
  }, [enabled]);

  useEffect(() => {
    if (!contact) return;
    if (!name) setName(contact.name || "");
    if (!email) setEmail(contact.email || "");
    if (!phone && contact.mobile) setPhone(contact.mobile);
  }, [contact, name, email, phone]);

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("requestProduct.unavailable")}</Text>
        <PrimaryButton label={t("nav.home")} onPress={() => router.replace("/(tabs)")} />
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={styles.lead}>{t("requestProduct.lead")}</Text>
        {!token ? (
          <Text style={styles.muted}>
            {t("requestProduct.loginOptional")}{" "}
            <Link href="/login" style={{ color: accent }}>
              {t("common.login")}
            </Link>
          </Text>
        ) : null}

        <LabeledInput
          label={t("requestProduct.productName")}
          value={productName}
          onChangeText={setProductName}
        />
        {meta?.platforms?.length ? (
          <SelectField
            label={t("requestProduct.platform")}
            value={platform}
            onChange={(value) => setPlatform(value)}
            options={[
              { value: "", label: "—" },
              ...meta.platforms.map((p) => ({ value: p.id, label: p.label })),
            ]}
          />
        ) : null}
        <LabeledInput
          label={t("requestProduct.notes")}
          value={notes}
          onChangeText={setNotes}
          multiline
          style={{ height: 100, textAlignVertical: "top" }}
          placeholder={t("requestProduct.notesPlaceholder")}
        />
        <LabeledInput
          label={t("requestProduct.name")}
          value={name}
          onChangeText={setName}
        />
        <LabeledInput
          label={t("requestProduct.email")}
          value={email}
          onChangeText={setEmail}
          autoCapitalize="none"
          keyboardType="email-address"
        />
        <LabeledInput
          label={t("requestProduct.phone")}
          value={phone}
          onChangeText={setPhone}
          keyboardType="phone-pad"
        />

        {status ? <Text style={styles.status}>{status}</Text> : null}
        <PrimaryButton
          label={busy ? t("requestProduct.submitting") : t("requestProduct.submit")}
          disabled={busy}
          onPress={() => {
            if (!productName.trim() || !name.trim() || !email.trim()) {
              setStatus(t("requestProduct.failed"));
              return;
            }
            setBusy(true);
            setStatus(null);
            void submitRequestProduct(
              {
                name: name.trim(),
                email: email.trim(),
                ...(phone.trim() ? { phone: phone.trim() } : {}),
                product_name: productName.trim(),
                ...(platform ? { platform } : {}),
                ...(notes.trim() ? { notes: notes.trim() } : {}),
              },
              token,
            )
              .then(() => {
                setProductName("");
                setPlatform("");
                setNotes("");
                setStatus(t("requestProduct.success"));
              })
              .catch((e) =>
                setStatus(
                  e instanceof Error ? e.message : t("requestProduct.failed"),
                ),
              )
              .finally(() => setBusy(false));
          }}
        />
      </FormScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  lead: { fontSize: 15, color: "#444", marginBottom: 12, lineHeight: 22 },
  muted: { color: "#777", marginBottom: 12 },
  status: { marginBottom: 8, color: "#333" },
});
