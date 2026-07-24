import { useState } from "react";
import { Pressable, StyleSheet, Text, TextInput, View } from "react-native";
import { repairStatus } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../src/components/ui";

type RepairRow = {
  status?: string;
  job_sheet_no?: string;
  invoice_no?: string;
  customer_name?: string;
  device?: string;
  brand?: string;
  model?: string;
  estimated_delivery?: string;
  notes?: string;
  [key: string]: unknown;
};

export default function RepairStatusScreen() {
  const { t, settings, accent } = useApp();
  const [searchType, setSearchType] = useState<
    "job_sheet_no" | "invoice_no" | "mobile_num"
  >("job_sheet_no");
  const [searchNumber, setSearchNumber] = useState("");
  const [repairs, setRepairs] = useState<RepairRow[]>([]);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  if (settings?.repair?.lookup_enabled === false) {
    return (
      <Screen>
        <Text>{t("repair.disabled")}</Text>
      </Screen>
    );
  }

  const types = [
    ["job_sheet_no", t("repair.jobSheet")] as const,
    ["invoice_no", t("repair.invoice")] as const,
    ...(settings?.repair?.lookup_by_mobile
      ? ([["mobile_num", t("repair.mobile")]] as const)
      : []),
  ];

  return (
    <Screen>
      <View style={styles.typeRow}>
        {types.map(([value, label]) => {
          const active = searchType === value;
          return (
            <Pressable
              key={value}
              style={[
                styles.chip,
                active && { borderColor: accent, backgroundColor: "#fff8e8" },
              ]}
              onPress={() => setSearchType(value)}
            >
              <Text style={styles.chipText}>{label}</Text>
            </Pressable>
          );
        })}
      </View>
      <TextInput
        style={styles.input}
        placeholder={t("repair.searchPlaceholder")}
        value={searchNumber}
        onChangeText={setSearchNumber}
      />
      <PrimaryButton
        label={busy ? t("common.loading") : t("repair.lookup")}
        disabled={busy || !searchNumber.trim()}
        onPress={() => {
          setBusy(true);
          setMessage(null);
          void repairStatus({
            search_type: searchType,
            search_number: searchNumber.trim(),
          })
            .then(({ data }) => {
              const list =
                (data as { repairs?: RepairRow[] })?.repairs || [];
              setRepairs(list);
              if (!list.length) {
                setMessage(t("repair.empty"));
              }
            })
            .catch((e) =>
              setMessage(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
      {message ? <Text style={styles.message}>{message}</Text> : null}
      {repairs.map((r, index) => (
        <View key={`${r.job_sheet_no || index}`} style={styles.card}>
          <Text style={styles.cardTitle}>
            {r.job_sheet_no || r.invoice_no || t("repair.result")}
          </Text>
          {r.status ? (
            <Text>
              {t("repair.status")}: {r.status}
            </Text>
          ) : null}
          {r.customer_name ? (
            <Text>
              {t("repair.customer")}: {r.customer_name}
            </Text>
          ) : null}
          {(r.device || r.brand || r.model) ? (
            <Text>
              {t("repair.device")}:{" "}
              {[r.brand, r.model, r.device].filter(Boolean).join(" ")}
            </Text>
          ) : null}
          {r.estimated_delivery ? (
            <Text>
              {t("repair.eta")}: {r.estimated_delivery}
            </Text>
          ) : null}
          {r.notes ? <Text>{r.notes}</Text> : null}
        </View>
      ))}
    </Screen>
  );
}

const styles = StyleSheet.create({
  typeRow: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 10 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#ddd",
    backgroundColor: "#fff",
  },
  chipText: { fontWeight: "600" },
  input: {
    backgroundColor: "#fff",
    borderRadius: 10,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 12,
    paddingVertical: 12,
    marginBottom: 10,
  },
  message: { marginTop: 12, color: "#666" },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginTop: 12,
    gap: 4,
  },
  cardTitle: { fontWeight: "800", fontSize: 16, marginBottom: 4 },
});
