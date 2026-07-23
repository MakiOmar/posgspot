import { useState } from "react";
import { StyleSheet, Text, TextInput, View } from "react-native";
import { repairStatus } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../src/components/ui";

export default function RepairStatusScreen() {
  const { t, settings } = useApp();
  const [searchType, setSearchType] = useState<
    "job_sheet_no" | "invoice_no" | "mobile_num"
  >("job_sheet_no");
  const [searchNumber, setSearchNumber] = useState("");
  const [result, setResult] = useState<string>("");
  const [busy, setBusy] = useState(false);

  if (settings?.repair?.lookup_enabled === false) {
    return (
      <Screen>
        <Text>Repair lookup is disabled.</Text>
      </Screen>
    );
  }

  return (
    <Screen>
      <View style={{ flexDirection: "row", gap: 8, marginBottom: 10 }}>
        {(
          [
            ["job_sheet_no", "Job sheet"],
            ["invoice_no", "Invoice"],
            ...(settings?.repair?.lookup_by_mobile
              ? ([["mobile_num", "Mobile"]] as const)
              : []),
          ] as const
        ).map(([value, label]) => (
          <PrimaryButton
            key={value}
            label={searchType === value ? `✓ ${label}` : label}
            onPress={() => setSearchType(value)}
          />
        ))}
      </View>
      <TextInput
        style={styles.input}
        placeholder="Search number"
        value={searchNumber}
        onChangeText={setSearchNumber}
      />
      <PrimaryButton
        label={busy ? t("common.loading") : "Lookup"}
        disabled={busy}
        onPress={() => {
          setBusy(true);
          void repairStatus({
            search_type: searchType,
            search_number: searchNumber,
          })
            .then(({ data }) => {
              const repairs = (data as { repairs?: Array<{ status?: string; job_sheet_no?: string }> })
                ?.repairs;
              if (!repairs?.length) {
                setResult("No repairs found");
                return;
              }
              setResult(
                repairs
                  .map(
                    (r) =>
                      `${r.job_sheet_no || "—"}: ${r.status || "unknown"}`,
                  )
                  .join("\n"),
              );
            })
            .catch((e) =>
              setResult(e instanceof Error ? e.message : t("common.error")),
            )
            .finally(() => setBusy(false));
        }}
      />
      {result ? (
        <Text style={{ marginTop: 16, lineHeight: 22 }}>{result}</Text>
      ) : null}
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
