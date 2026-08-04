import {
  Dimensions,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import Svg, { Defs, LinearGradient, Rect, Stop } from "react-native-svg";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

const SCREEN_W = Dimensions.get("window").width;

type Props = {
  inStock: boolean;
  qty: number;
  onQtyChange: (qty: number) => void;
  adding: boolean;
  onAddToCart: () => void;
  onCheckAvailability: () => void;
};

/** Sticky bottom add-to-cart / availability action bar. */
export function ProductStickyBar({
  inStock,
  qty,
  onQtyChange,
  adding,
  onAddToCart,
  onCheckAvailability,
}: Props) {
  const { t, accent } = useApp();
  const { row, textAlign } = useRtl();
  const insets = useSafeAreaInsets();

  return (
    <View
      style={[
        styles.sticky,
        { paddingBottom: Math.max(insets.bottom, 10) },
      ]}
      pointerEvents="box-none"
    >
      {/* Soft fade into content above the action bar */}
      <View style={styles.fade} pointerEvents="none">
        <Svg width={SCREEN_W} height={36}>
          <Defs>
            <LinearGradient id="pdpFade" x1="0" y1="0" x2="0" y2="1">
              <Stop offset="0" stopColor="#F7F7F5" stopOpacity="0" />
              <Stop offset="1" stopColor="#F7F7F5" stopOpacity="0.96" />
            </LinearGradient>
          </Defs>
          <Rect x="0" y="0" width={SCREEN_W} height="36" fill="url(#pdpFade)" />
        </Svg>
      </View>

      <View style={styles.stickyInner}>
        <View style={[styles.actionRow, { flexDirection: row }]}>
          {inStock ? (
            <View style={[styles.qtyCtrl, { flexDirection: row }]}>
              <Pressable
                style={styles.qtyBtn}
                disabled={adding}
                onPress={() => onQtyChange(Math.max(1, qty - 1))}
              >
                <Text style={styles.qtyBtnText}>−</Text>
              </Pressable>
              <Text style={styles.qty}>{qty}</Text>
              <Pressable
                style={styles.qtyBtn}
                disabled={adding}
                onPress={() => onQtyChange(qty + 1)}
              >
                <Text style={styles.qtyBtnText}>+</Text>
              </Pressable>
            </View>
          ) : null}

          <Pressable
            style={[
              styles.cta,
              {
                backgroundColor: accent,
                opacity: adding ? 0.6 : 1,
              },
            ]}
            disabled={adding}
            onPress={() => {
              if (inStock) onAddToCart();
              else onCheckAvailability();
            }}
          >
            <Text style={styles.ctaText} numberOfLines={1}>
              {inStock
                ? adding
                  ? t("catalog.addingToCart")
                  : t("common.addToCart")
                : t("catalog.checkAvailability")}
            </Text>
          </Pressable>
        </View>

        {inStock ? (
          <Pressable style={styles.availLink} onPress={onCheckAvailability}>
            <Text style={[styles.availLinkText, { textAlign }]}>
              {t("catalog.checkAvailability")}
            </Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  sticky: {
    position: "absolute",
    left: 0,
    right: 0,
    bottom: 0,
  },
  fade: {
    height: 36,
    width: "100%",
  },
  stickyInner: {
    backgroundColor: "rgba(247, 247, 245, 0.96)",
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: "rgba(0,0,0,0.08)",
    paddingHorizontal: 16,
    paddingTop: 10,
    gap: 8,
  },
  actionRow: {
    alignItems: "center",
    gap: 10,
  },
  qtyCtrl: {
    alignItems: "center",
    gap: 8,
    backgroundColor: "#fff",
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 6,
    paddingVertical: 4,
  },
  qtyBtn: {
    width: 36,
    height: 40,
    borderRadius: 8,
    alignItems: "center",
    justifyContent: "center",
  },
  qtyBtnText: { fontSize: 22, fontWeight: "700", color: "#222" },
  qty: { minWidth: 28, textAlign: "center", fontWeight: "800", fontSize: 16 },
  cta: {
    flex: 1,
    minHeight: 48,
    borderRadius: 12,
    alignItems: "center",
    justifyContent: "center",
    paddingHorizontal: 14,
  },
  ctaText: { color: "#111", fontWeight: "800", fontSize: 15 },
  availLink: { alignItems: "center", paddingBottom: 2 },
  availLinkText: { color: "#555", fontWeight: "600", fontSize: 13 },
});
