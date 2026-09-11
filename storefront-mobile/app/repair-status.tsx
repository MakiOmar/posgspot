import { useCallback, useEffect, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { Link } from "expo-router";
import { fetchAccountRepairs, repairStatus } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { LabeledInput } from "../src/components/LabeledInput";
import {
  FormScrollView,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../src/components/ui";

type RepairRow = {
  status?: string | null;
  status_color?: string | null;
  job_sheet_no?: string;
  invoice_no?: string;
  customer_name?: string;
  device?: string | null;
  brand?: string | null;
  model?: string | null;
  serial_no?: string | null;
  due_date_label?: string | null;
  estimated_delivery?: string;
  notes?: string;
  activities?: Array<{
    date?: string | null;
    date_label?: string | null;
    action?: string;
    by?: string;
    note?: string | null;
  }>;
  [key: string]: unknown;
};

function RepairCard({
  repair,
  t,
}: {
  repair: RepairRow;
  t: (key: string) => string;
}) {
  return (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>
        {repair.job_sheet_no || repair.invoice_no || t("repair.result")}
      </Text>
      {repair.status ? (
        <Text>
          {t("repair.status")}: {repair.status}
        </Text>
      ) : null}
      {repair.customer_name ? (
        <Text>
          {t("repair.customer")}: {repair.customer_name}
        </Text>
      ) : null}
      {repair.device || repair.brand || repair.model ? (
        <Text>
          {t("repair.device")}:{" "}
          {[repair.brand, repair.model, repair.device].filter(Boolean).join(" ")}
        </Text>
      ) : null}
      {repair.serial_no ? (
        <Text>
          {t("repair.serialNo")}: {repair.serial_no}
        </Text>
      ) : null}
      {repair.due_date_label || repair.estimated_delivery ? (
        <Text>
          {t("repair.eta")}: {repair.due_date_label || repair.estimated_delivery}
        </Text>
      ) : null}
      {repair.notes ? <Text>{repair.notes}</Text> : null}
      {(repair.activities || []).slice(0, 3).map((activity, index) => (
        <Text key={`${repair.job_sheet_no}-a-${index}`} style={styles.activity}>
          {activity.date_label || activity.date}: {activity.action}
        </Text>
      ))}
    </View>
  );
}

export default function RepairStatusScreen() {
  const { t, settings, accent, token } = useApp();
  const [searchType, setSearchType] = useState<
    "job_sheet_no" | "invoice_no" | "mobile_num"
  >("mobile_num");
  const [searchNumber, setSearchNumber] = useState("");
  const [repairs, setRepairs] = useState<RepairRow[]>([]);
  const [myRepairs, setMyRepairs] = useState<RepairRow[]>([]);
  const [myLoaded, setMyLoaded] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const loadMine = useCallback(() => {
    if (!token) {
      setMyRepairs([]);
      setMyLoaded(true);
      return;
    }
    setMyLoaded(false);
    void fetchAccountRepairs(token)
      .then(({ data }) => {
        setMyRepairs(
          ((data as { repairs?: RepairRow[] })?.repairs || []) as RepairRow[],
        );
      })
      .catch(() => setMyRepairs([]))
      .finally(() => setMyLoaded(true));
  }, [token]);

  useEffect(() => {
    loadMine();
  }, [loadMine]);

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
    ...(settings?.repair?.lookup_by_mobile !== false
      ? ([["mobile_num", t("repair.mobile")]] as const)
      : []),
  ];

  // Logged-in: auto-list only. Guests: lookup form only.
  if (token) {
    return (
      <Screen padded={false} avoidKeyboard={false}>
        <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
          <Text style={styles.sectionTitle}>{t("repair.myRepairs")}</Text>
          {!myLoaded ? (
            <LoadingBlock />
          ) : myRepairs.length === 0 ? (
            <Text style={styles.message}>{t("repair.myRepairsEmpty")}</Text>
          ) : (
            myRepairs.map((r, index) => (
              <RepairCard
                key={`mine-${r.job_sheet_no || index}`}
                repair={r}
                t={t}
              />
            ))
          )}
        </FormScrollView>
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={styles.lead}>{t("repair.guestIntro")}</Text>
        <Text style={styles.signInHint}>
          {t("repair.myRepairsSignIn")}{" "}
          <Link href="/login" style={{ color: accent, fontWeight: "700" }}>
            {t("auth.signIn")}
          </Link>
        </Text>

        <Text style={styles.sectionTitle}>{t("repair.lookup")}</Text>
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
        <LabeledInput
          label={t("repair.searchPlaceholder")}
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
          <RepairCard
            key={`search-${r.job_sheet_no || index}`}
            repair={r}
            t={t}
          />
        ))}
      </FormScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  lead: { color: "#555", lineHeight: 20, marginBottom: 8 },
  signInHint: { color: "#666", marginBottom: 16, lineHeight: 20 },
  sectionTitle: { fontSize: 17, fontWeight: "800", marginBottom: 10 },
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
  message: { marginTop: 8, marginBottom: 8, color: "#666", lineHeight: 20 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginTop: 12,
    gap: 4,
  },
  cardTitle: { fontWeight: "800", fontSize: 16, marginBottom: 4 },
  activity: { marginTop: 4, color: "#555", fontSize: 13 },
});
