import { useCallback, useEffect, useState } from "react";
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from "react-native";
import { availableCoupons } from "../../lib/api";
import { toCartApiItem } from "../../lib/cart";
import type { AvailableCouponInfo, CartItem } from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

type Props = {
  items: CartItem[];
  token: string | null;
  appliedCodes: string[];
  applying?: boolean;
  onSelect: (code: string) => void | Promise<void>;
};

/**
 * Expandable list of eligible promo codes for the current cart (Qwik CouponPicker parity).
 */
export function CouponPicker({
  items,
  token,
  appliedCodes,
  applying,
  onSelect,
}: Props) {
  const { t, accent, settings } = useApp();
  const { textAlign, writingDirection, row } = useRtl();
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [coupons, setCoupons] = useState<AvailableCouponInfo[]>([]);
  const [selecting, setSelecting] = useState<string | null>(null);

  const allowStacking = !!settings?.promo_codes?.allow_stacking;
  const canShow =
    items.length > 0 &&
    !!token &&
    (allowStacking || appliedCodes.length === 0);

  const load = useCallback(async () => {
    if (!token || items.length === 0) {
      setCoupons([]);
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const exclude = allowStacking
        ? appliedCodes.map((c) => c.trim()).filter(Boolean)
        : [];
      const { data } = await availableCoupons(
        {
          items: items.map(toCartApiItem),
          exclude_codes: exclude.length ? exclude : undefined,
        },
        token,
      );
      setCoupons(data.coupons ?? []);
    } catch (e) {
      setCoupons([]);
      setError(e instanceof Error ? e.message : t("coupon.pickerLoadError"));
    } finally {
      setLoading(false);
    }
  }, [token, items, allowStacking, appliedCodes, t]);

  useEffect(() => {
    if (open) void load();
  }, [open, load]);

  if (!canShow) return null;

  const formatSavings = (coupon: AvailableCouponInfo) => {
    if (coupon.free_shipping && coupon.discount_amount <= 0) {
      return t("coupon.pickerFreeShipping");
    }
    if (coupon.free_shipping && coupon.discount_amount > 0) {
      return t("coupon.pickerSavePlusShipping", {
        amount: `${Number(coupon.total_savings).toFixed(2)} EGP`,
      });
    }
    return t("coupon.pickerSave", {
      amount: `${Number(coupon.total_savings).toFixed(2)} EGP`,
    });
  };

  return (
    <View style={styles.wrap}>
      <Pressable
        style={[styles.toggle, open && { borderColor: accent }]}
        disabled={!!applying}
        onPress={() => setOpen((v) => !v)}
      >
        <Text style={[styles.toggleText, { textAlign, writingDirection }]}>
          {open ? t("coupon.pickerHide") : t("coupon.pickerShow")}
        </Text>
        <Text style={styles.chevron}>{open ? "▴" : "▾"}</Text>
      </Pressable>

      {open ? (
        <View style={styles.panel}>
          {loading ? (
            <View style={styles.statusRow}>
              <ActivityIndicator color={accent} />
              <Text style={styles.status}>{t("coupon.pickerLoading")}</Text>
            </View>
          ) : error ? (
            <Text style={[styles.error, { textAlign }]}>{error}</Text>
          ) : coupons.length === 0 ? (
            <Text style={[styles.status, { textAlign }]}>
              {t("coupon.pickerEmpty")}
            </Text>
          ) : (
            coupons.map((coupon) => {
              const isApplied = appliedCodes.some(
                (c) => c.toUpperCase() === coupon.code.toUpperCase(),
              );
              const isSelecting = selecting === coupon.code;
              return (
                <Pressable
                  key={coupon.id}
                  style={[
                    styles.card,
                    isApplied && {
                      borderColor: accent,
                      backgroundColor: "#fff8e8",
                    },
                  ]}
                  disabled={!!applying || isApplied || isSelecting}
                  onPress={async () => {
                    setSelecting(coupon.code);
                    try {
                      await onSelect(coupon.code);
                      if (!allowStacking) setOpen(false);
                    } finally {
                      setSelecting(null);
                    }
                  }}
                >
                  <View style={[styles.cardTop, { flexDirection: row }]}>
                    <Text style={styles.code}>{coupon.code}</Text>
                    {isApplied ? (
                      <Text style={[styles.badge, { color: accent }]}>
                        {t("coupon.pickerApplied")}
                      </Text>
                    ) : null}
                  </View>
                  <Text
                    style={[styles.name, { textAlign, writingDirection }]}
                  >
                    {coupon.name}
                  </Text>
                  {coupon.label ? (
                    <Text
                      style={[styles.label, { textAlign, writingDirection }]}
                    >
                      {coupon.label}
                    </Text>
                  ) : null}
                  {coupon.description ? (
                    <Text
                      style={[styles.desc, { textAlign, writingDirection }]}
                    >
                      {coupon.description}
                    </Text>
                  ) : null}
                  <Text style={[styles.savings, { textAlign }]}>
                    {isSelecting
                      ? t("common.loading")
                      : formatSavings(coupon)}
                  </Text>
                </Pressable>
              );
            })
          )}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 4, gap: 8 },
  toggle: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    backgroundColor: "#fff",
    borderWidth: 1,
    borderColor: "#e5e5e5",
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
  },
  toggleText: { fontWeight: "700", color: "#222", flex: 1 },
  chevron: { color: "#666", fontSize: 14, marginHorizontal: 4 },
  panel: { gap: 8 },
  statusRow: { flexDirection: "row", alignItems: "center", gap: 8, padding: 8 },
  status: { color: "#666", paddingVertical: 6 },
  error: { color: "#B00020", paddingVertical: 6 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    padding: 12,
    gap: 4,
  },
  cardTop: {
    alignItems: "center",
    justifyContent: "space-between",
    marginBottom: 2,
  },
  code: {
    fontWeight: "800",
    letterSpacing: 0.5,
    color: "#111",
    fontSize: 15,
  },
  badge: { fontWeight: "700", fontSize: 12 },
  name: { fontWeight: "700", color: "#222" },
  label: { color: "#666", fontSize: 13 },
  desc: { color: "#888", fontSize: 12 },
  savings: { marginTop: 4, fontWeight: "800", color: "#0B6E4F" },
});
