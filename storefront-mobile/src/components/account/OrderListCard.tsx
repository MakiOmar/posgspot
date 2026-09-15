import type { ReactNode } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { Link } from "expo-router";
import type { AccountOrder } from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

type BadgeColors = { backgroundColor: string; color: string };

/** Soft badge palette by payment_status (case-insensitive). */
export function paymentStatusBadgeColors(status: string | undefined): BadgeColors {
  switch ((status ?? "").trim().toLowerCase()) {
    case "paid":
      return { backgroundColor: "#E8F5E9", color: "#1B5E20" };
    case "pending":
      return { backgroundColor: "#FFF8E1", color: "#F57F17" };
    case "due":
      return { backgroundColor: "#FFF3E0", color: "#E65100" };
    case "failed":
      return { backgroundColor: "#FFEBEE", color: "#B71C1C" };
    default:
      return { backgroundColor: "#F0F0F0", color: "#555555" };
  }
}

/** Tappable order row: title, status badge, total, circular chevron. */
export function OrderListCard({
  order,
  footer,
}: {
  order: AccountOrder;
  footer?: ReactNode;
}) {
  const { accent } = useApp();
  const { row, textAlign, writingDirection, isRtl } = useRtl();
  const badge = paymentStatusBadgeColors(order.payment_status);
  const title =
    order.invoice_no || order.storefront_order_id || `#${order.id}`;
  const total =
    order.final_total != null
      ? `${Number(order.final_total).toFixed(2)} EGP`
      : null;
  const statusLabel = (order.payment_status || "—").trim() || "—";

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
              <View
                style={[
                  styles.badge,
                  { backgroundColor: badge.backgroundColor },
                ]}
              >
                <Text style={[styles.badgeText, { color: badge.color }]}>
                  {statusLabel}
                </Text>
              </View>
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
    textTransform: "capitalize",
  },
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
