import { useCallback, useEffect, useState } from "react";
import { Linking, Pressable, StyleSheet, Text, View } from "react-native";
import { Link } from "expo-router";
import { fetchOrders, trackOrderLookup } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { LabeledInput } from "../src/components/LabeledInput";
import { OrderListCard } from "../src/components/account/OrderListCard";
import {
  FormScrollView,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../src/components/ui";
import type { AccountOrder, TrackedOrder } from "../src/lib/types";

function looksLikeEmail(value: string): boolean {
  return value.includes("@");
}

function TrackedCard({
  order,
  t,
}: {
  order: TrackedOrder;
  t: (key: string) => string;
}) {
  return (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>
        {order.invoice_no || order.storefront_order_id || `#${order.id}`}
      </Text>
      {order.status ? (
        <Text>
          {t("trackOrder.status")}: {order.status}
        </Text>
      ) : null}
      {order.payment_status ? (
        <Text>
          {t("trackOrder.payment")}: {order.payment_status}
        </Text>
      ) : null}
      {order.shipping_status ? (
        <Text>
          {t("trackOrder.shipping")}: {order.shipping_status}
        </Text>
      ) : null}
      {order.shipping_carrier ? (
        <Text>
          {t("trackOrder.carrier")}: {order.shipping_carrier}
        </Text>
      ) : null}
      {order.shipping_tracking_number || order.shipping_tracking_url ? (
        <Pressable
          onPress={() => {
            if (order.shipping_tracking_url) {
              void Linking.openURL(order.shipping_tracking_url);
            }
          }}
        >
          <Text style={order.shipping_tracking_url ? styles.link : undefined}>
            {t("trackOrder.tracking")}:{" "}
            {order.shipping_tracking_number || order.shipping_tracking_url}
          </Text>
        </Pressable>
      ) : null}
      {order.final_total != null ? (
        <Text>
          {t("trackOrder.total")}: {Number(order.final_total).toFixed(2)} EGP
        </Text>
      ) : null}
      {(order.lines || []).map((line, index) => (
        <Text key={`${order.id}-l-${index}`} style={styles.line}>
          {line.product_name || "—"} × {line.quantity}
        </Text>
      ))}
    </View>
  );
}

export default function TrackOrderScreen() {
  const { t, token, accent } = useApp();
  const [invoiceNo, setInvoiceNo] = useState("");
  const [phoneOrEmail, setPhoneOrEmail] = useState("");
  const [tracked, setTracked] = useState<TrackedOrder | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [myOrders, setMyOrders] = useState<AccountOrder[]>([]);
  const [myLoaded, setMyLoaded] = useState(false);

  const loadMine = useCallback(() => {
    if (!token) {
      setMyOrders([]);
      setMyLoaded(true);
      return;
    }
    setMyLoaded(false);
    void fetchOrders(token, { perPage: 10 })
      .then(({ data }) => setMyOrders(data || []))
      .catch(() => setMyOrders([]))
      .finally(() => setMyLoaded(true));
  }, [token]);

  useEffect(() => {
    loadMine();
  }, [loadMine]);

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={styles.lead}>{t("trackOrder.lead")}</Text>

        {token ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>{t("trackOrder.yourOrders")}</Text>
            {!myLoaded ? <LoadingBlock /> : null}
            {myLoaded && myOrders.length === 0 ? (
              <Text style={styles.muted}>{t("trackOrder.myOrdersEmpty")}</Text>
            ) : null}
            {myOrders.map((order) => (
              <OrderListCard key={order.id} order={order} />
            ))}
          </View>
        ) : (
          <Text style={styles.muted}>
            {t("trackOrder.signInHint")}{" "}
            <Link href="/login" style={{ color: accent }}>
              {t("common.login")}
            </Link>
          </Text>
        )}

        <View style={styles.section}>
          <LabeledInput
            label={t("trackOrder.invoiceNo")}
            value={invoiceNo}
            onChangeText={setInvoiceNo}
            autoCapitalize="none"
          />
          <LabeledInput
            label={t("trackOrder.phoneOrEmail")}
            value={phoneOrEmail}
            onChangeText={setPhoneOrEmail}
            autoCapitalize="none"
            keyboardType="email-address"
          />
          {message ? <Text style={styles.message}>{message}</Text> : null}
          <PrimaryButton
            label={busy ? t("trackOrder.submitting") : t("trackOrder.submit")}
            disabled={busy}
            onPress={() => {
              const invoice = invoiceNo.trim();
              const contact = phoneOrEmail.trim();
              if (!invoice || !contact) {
                setMessage(t("trackOrder.notFound"));
                return;
              }
              setBusy(true);
              setMessage(null);
              const payload = looksLikeEmail(contact)
                ? { invoice_no: invoice, email: contact }
                : { invoice_no: invoice, phone: contact };
              void trackOrderLookup(payload)
                .then(({ data }) => {
                  setTracked(data);
                  setMessage(null);
                })
                .catch((e) => {
                  setTracked(null);
                  setMessage(
                    e instanceof Error ? e.message : t("trackOrder.notFound"),
                  );
                })
                .finally(() => setBusy(false));
            }}
          />
          {tracked ? <TrackedCard order={tracked} t={t} /> : null}
        </View>
      </FormScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  lead: { fontSize: 15, color: "#444", marginBottom: 16, lineHeight: 22 },
  section: { marginBottom: 24 },
  sectionTitle: { fontSize: 17, fontWeight: "800", marginBottom: 10, color: "#111" },
  muted: { color: "#777", marginBottom: 12 },
  message: { color: "#B71C1C", marginBottom: 8 },
  card: {
    marginTop: 16,
    padding: 14,
    borderRadius: 12,
    backgroundColor: "#fff",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e0e0e0",
    gap: 4,
  },
  cardTitle: { fontSize: 16, fontWeight: "800", marginBottom: 4 },
  line: { color: "#555", fontSize: 13 },
  link: { color: "#1565C0", textDecorationLine: "underline" },
});
