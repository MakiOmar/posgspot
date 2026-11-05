import type { ReactNode } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { Link } from "expo-router";
import type { AccountOrder } from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";
import {
  PAYMENT_BADGE_COLORS,
  paymentStatusLabel,
  paymentStatusTone,
  shippingStatusLabel,
} from "../../lib/order-status";

/** Localized payment status pill (paid / not paid / partial / pending / failed). */
export function PaymentStatusBadge({ status }: { status?: string | null }) {
  const { locale } = useApp();
  const badge = PAYMENT_BADGE_COLORS[paymentStatusTone(status)];
  return (
    <View style={[styles.badge, { backgroundColor: badge.backgroundColor }]}>
      <Text style={[styles.badgeText, { color: badge.color }]}>
        {paymentStatusLabel(locale, status)}
      </Text>
    </View>
  );
}

/** Tappable order row: title, status badge, total, circular chevron. */
export function OrderListCard({
  order,
  footer,
}: {
  order: AccountOrder;
  footer?: ReactNode;
}) {
  const { accent, locale } = useApp();
  const { row, textAlign, writingDirection, isRtl } = useRtl();
  const title =
    order.invoice_no || order.storefront_order_id || `#${order.id}`;
  const total =
    order.final_total != null
      ? `${Number(order.final_total).toFixed(2)} EGP`
      : null;
  const shipping = shippingStatusLabel(locale, order.shipping_status);

  return (
    <View style={styles.card}>
      <Link href={`/account/orders/${order.id}`} asChild>
        <Pressable
          style={[styles.row, { flexDirection: row }]}
          accessibilityRole="button"
          accessibilityLabel={title}
        >
          <View style={styles.body}>
            <Text
              style={[styles.title, { textAlign, writingDirection }]}
              numberOfLines={1}
            >
              {title}
            </Text>
            <View style={[styles.metaRow, { flexDirection: row }]}>
              <PaymentStatusBadge status={order.payment_status} />
              {shipping ? (
                <Text style={[styles.shipping, { textAlign, writingDirection }]}>
                  {shipping}
                </Text>
              ) : null}
              {total ? (
                <Text style={[styles.total, { textAlign, writingDirection }]}>
                  {total}
                </Text>
              ) : null}
            </View>
          </View>
          <View style={[styles.chevronCircle, { borderColor: accent }]}>
            <Ionicons
              name={isRtl ? "chevron-back" : "chevron-forward"}
              size={18}
              color={accent}
            />
          </View>
        </Pressable>
      </Link>
      {footer}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: "#fff",
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 10,
    marginBottom: 8,
  },
  row: {
    alignItems: "center",
    gap: 12,
  },
  body: { flex: 1, minWidth: 0 },
  title: { fontWeight: "800", fontSize: 16, marginBottom: 8, color: "#111" },
  metaRow: {
    alignItems: "center",
    flexWrap: "wrap",
    gap: 8,
  },
  badge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  badgeText: {
    fontSize: 12,
    fontWeight: "700",
  },
  shipping: { color: "#333", fontSize: 12, fontWeight: "600" },
  total: { color: "#555", fontSize: 13, fontWeight: "500" },
  chevronCircle: {
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 1.5,
    alignItems: "center",
    justifyContent: "center",
  },
});
