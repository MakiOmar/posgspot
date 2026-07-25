import { Pressable, StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

export type ProductSort = "newest" | "name" | "price_asc" | "price_desc" | "default";

type Props = {
  sort: ProductSort;
  inStockOnly: boolean;
  onSortChange: (sort: ProductSort) => void;
  onInStockChange: (value: boolean) => void;
};

const SORTS: Array<{ id: ProductSort; labelKey: string }> = [
  { id: "newest", labelKey: "plp.sortNewest" },
  { id: "name", labelKey: "plp.sortName" },
  { id: "price_asc", labelKey: "plp.sortPriceAsc" },
  { id: "price_desc", labelKey: "plp.sortPriceDesc" },
];

export function ProductListToolbar({
  sort,
  inStockOnly,
  onSortChange,
  onInStockChange,
}: Props) {
  const { t, accent } = useApp();
  const { row, textAlign, writingDirection } = useRtl();

  return (
    <View style={styles.wrap}>
      <View style={[styles.row, { flexDirection: row }]}>
        {SORTS.map((s) => {
          const active = sort === s.id;
          return (
            <Pressable
              key={s.id}
              style={[styles.chip, active && { borderColor: accent, backgroundColor: "#fff8e8" }]}
              onPress={() => onSortChange(s.id)}
            >
              <Text style={[styles.chipText, { textAlign, writingDirection }]}>
                {t(s.labelKey)}
              </Text>
            </Pressable>
          );
        })}
      </View>
      <Pressable
        style={[styles.stock, inStockOnly && { borderColor: accent, backgroundColor: "#fff8e8" }]}
        onPress={() => onInStockChange(!inStockOnly)}
      >
        <Text style={[styles.chipText, { textAlign, writingDirection }]}>
          {inStockOnly ? `✓ ${t("plp.inStockOnly")}` : t("plp.inStockOnly")}
        </Text>
      </Pressable>
    </View>
  );
}

/** Map UI sort to Storefront API `sort` query. */
export function sortToApi(sort: ProductSort): string {
  switch (sort) {
    case "name":
      return "name";
    case "price_asc":
      return "price_asc";
    case "price_desc":
      return "price_desc";
    case "default":
      return "default";
    default:
      return "newest";
  }
}

const styles = StyleSheet.create({
  wrap: { marginBottom: 12, gap: 8 },
  row: { flexWrap: "wrap", gap: 6 },
  chip: {
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 6,
    backgroundColor: "#fff",
  },
  stock: {
    alignSelf: "flex-start",
    borderWidth: 1,
    borderColor: "#ddd",
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: "#fff",
  },
  chipText: { fontSize: 12, fontWeight: "600", color: "#222" },
});
