import { useCallback, useEffect, useRef, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { validateRewardPoints } from "../../lib/api";
import type { RewardPointsBalance } from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { LabeledInput } from "../LabeledInput";
import { useRtl } from "../../lib/rtl";

type Props = {
  token: string;
  balance: RewardPointsBalance;
  orderTotal: number;
  pointsToRedeem: number;
  onChange: (points: number, redeemAmount: number, isValid: boolean) => void;
};

/** Checkout reward points redeem with API validation (Qwik parity). */
export function RewardPointsRedeem({
  token,
  balance,
  orderTotal,
  pointsToRedeem,
  onChange,
}: Props) {
  const { t, accent } = useApp();
  const { textAlign, writingDirection, row } = useRtl();
  const [input, setInput] = useState(
    pointsToRedeem > 0 ? String(pointsToRedeem) : "",
  );
  const [validating, setValidating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [redeemAmount, setRedeemAmount] = useState(0);
  const onChangeRef = useRef(onChange);
  onChangeRef.current = onChange;

  const maxPoints = Number(balance.max_redeem_points ?? 0);
  const available = Number(
    balance.available ?? balance.balance ?? balance.points ?? 0,
  );
  const title = balance.name || t("rewards.defaultName");

  const runValidation = useCallback(
    async (points: number) => {
      setValidating(true);
      setError(null);
      try {
        const { data } = await validateRewardPoints(token, {
          requested_points: points,
          order_total: orderTotal,
        });
        setRedeemAmount(Number(data.redeem_amount) || 0);
        if (!data.is_valid && data.message) {
          setError(data.message);
        }
        onChangeRef.current(
          data.requested_points,
          data.redeem_amount,
          data.is_valid,
        );
      } catch {
        setError(t("rewards.validateError"));
        setRedeemAmount(0);
        onChangeRef.current(0, 0, false);
      } finally {
        setValidating(false);
      }
    },
    [token, orderTotal, t],
  );

  useEffect(() => {
    const points = parseInt(input, 10) || 0;
    const timer = setTimeout(() => void runValidation(points), 280);
    return () => clearTimeout(timer);
  }, [input, orderTotal, runValidation]);

  if (balance.enabled === false || maxPoints <= 0) {
    return null;
  }

  return (
    <View style={styles.wrap}>
      <Text style={[styles.title, { textAlign, writingDirection }]}>{title}</Text>
      <Text style={[styles.hint, { textAlign, writingDirection }]}>
        {t("rewards.hint", { available })}
        {maxPoints > 0 ? t("rewards.hintMax", { max: maxPoints }) : null}.
      </Text>
      <LabeledInput
        label={t("rewards.pointsToRedeem")}
        value={input}
        onChangeText={(v) => setInput(v.replace(/\D/g, ""))}
        keyboardType="number-pad"
        placeholder="0"
        editable={!validating}
      />
      <View style={[styles.row, { flexDirection: row }]}>
        <Pressable
          style={[styles.btn, { borderColor: accent }]}
          disabled={validating}
          onPress={() => {
            setInput(String(maxPoints));
          }}
        >
          <Text style={styles.btnText}>{t("rewards.useMax")}</Text>
        </Pressable>
        {input ? (
          <Pressable
            style={styles.btn}
            disabled={validating}
            onPress={() => setInput("")}
          >
            <Text style={styles.btnText}>{t("rewards.clear")}</Text>
          </Pressable>
        ) : null}
      </View>
      {validating ? (
        <Text style={[styles.status, { textAlign }]}>{t("rewards.checking")}</Text>
      ) : null}
      {error ? (
        <Text style={[styles.error, { textAlign }]}>{error}</Text>
      ) : null}
      {redeemAmount > 0 && !error ? (
        <Text style={[styles.savings, { textAlign }]}>
          {t("rewards.discount")}{" "}
          <Text style={styles.savingsStrong}>
            {redeemAmount.toFixed(2)} EGP
          </Text>
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { marginTop: 8, marginBottom: 8, gap: 6 },
  title: { fontWeight: "800", fontSize: 16, color: "#111" },
  hint: { color: "#666", lineHeight: 18 },
  row: { gap: 8, marginBottom: 4 },
  btn: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 8,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: "#fff",
  },
  btnText: { fontWeight: "700", color: "#222" },
  status: { color: "#666" },
  error: { color: "#B00020" },
  savings: { color: "#0B6E4F", fontWeight: "600" },
  savingsStrong: { fontWeight: "800" },
});
