import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import {
  FlatList,
  Image,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useRouter } from "expo-router";
import type { KeyboardAwareScrollViewRef } from "react-native-keyboard-controller";
import {
  ApiError,
  fetchCustomBundleMeta,
  fetchCustomBundleProducts,
  fetchProduct,
} from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { useCart } from "../src/contexts/CartContext";
import { toast } from "../src/lib/toast";
import {
  FormScrollView,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../src/components/ui";
import type {
  CartItem,
  CustomBundleMeta,
  ProductSummary,
} from "../src/lib/types";

type BundleLine = {
  key: string;
  productId: number;
  variationId: number;
  slug?: string | null;
  name: string;
  variationName: string;
  price: number;
  quantity: number;
  imageUrl?: string | null;
};

type VariationOption = {
  id: number;
  name: string;
  price: number;
};

export default function CustomBundleScreen() {
  const { t, settings, locale, accent } = useApp();
  const { addItem } = useCart();
  const router = useRouter();
  const enabled = settings?.custom_bundle?.enabled === true;

  const [meta, setMeta] = useState<CustomBundleMeta | null>(null);
  const [platform, setPlatform] = useState("");
  const [tab, setTab] = useState("all");
  const [tabs, setTabs] = useState<Array<{ id: string; label: string }>>([
    { id: "all", label: t("customBundle.tabAll") },
  ]);
  const [query, setQuery] = useState("");
  const [products, setProducts] = useState<ProductSummary[]>([]);
  const [loading, setLoading] = useState(false);
  const [selection, setSelection] = useState<BundleLine[]>([]);
  const [picker, setPicker] = useState<{
    productId: number;
    name: string;
    slug?: string | null;
    imageUrl?: string | null;
    options: VariationOption[];
  } | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const scrollRef = useRef<KeyboardAwareScrollViewRef>(null);
  const trayOffsetY = useRef(0);

  const minItems = meta?.min_items ?? settings?.custom_bundle?.min_items ?? 2;
  const maxItems = meta?.max_items ?? settings?.custom_bundle?.max_items ?? 15;
  const selectionCount = selection.reduce((sum, row) => sum + row.quantity, 0);
  const selectionTotal = selection.reduce(
    (sum, row) => sum + row.price * row.quantity,
    0,
  );

  useEffect(() => {
    if (!enabled) return;
    void fetchCustomBundleMeta(locale)
      .then(({ data }) => {
        setMeta(data);
        if (data.platforms?.length && !platform) {
          // leave platform empty until user picks
        }
      })
      .catch(() => setMeta(null));
  }, [enabled, locale]);

  useEffect(() => {
    if (!platform) {
      setTabs([{ id: "all", label: t("customBundle.tabAll") }]);
      setTab("all");
      setProducts([]);
      return;
    }
    void fetchCustomBundleMeta(locale, platform)
      .then(({ data }) => {
        const nextTabs =
          data.tabs?.length > 0
            ? data.tabs
            : [{ id: "all", label: t("customBundle.tabAll") }];
        setTabs(nextTabs);
        if (!nextTabs.some((row) => row.id === tab)) {
          setTab("all");
        }
      })
      .catch(() => {
        setTabs([{ id: "all", label: t("customBundle.tabAll") }]);
        setTab("all");
      });
  }, [platform, locale, t]);

  const loadProducts = useCallback(() => {
    if (!platform) {
      setProducts([]);
      return;
    }
    setLoading(true);
    void fetchCustomBundleProducts(
      {
        platform,
        tab,
        q: query.trim() || undefined,
        per_page: 40,
      },
      locale,
    )
      .then(({ data }) => setProducts(data || []))
      .catch(() => {
        setProducts([]);
        toast.error(t("customBundle.loadFailed"));
      })
      .finally(() => setLoading(false));
  }, [platform, tab, query, locale, t]);

  useEffect(() => {
    loadProducts();
  }, [loadProducts]);

  const addLine = (line: BundleLine) => {
    const count = selection.reduce((sum, row) => sum + row.quantity, 0);
    if (count >= maxItems) {
      toast.error(t("customBundle.maxReached", { max: maxItems }));
      return;
    }
    setSelection((prev) => {
      const existing = prev.find((row) => row.key === line.key);
      if (existing) {
        if (count + 1 > maxItems) {
          toast.error(t("customBundle.maxReached", { max: maxItems }));
          return prev;
        }
        return prev.map((row) =>
          row.key === line.key
            ? { ...row, quantity: row.quantity + 1 }
            : row,
        );
      }
      return [...prev, line];
    });
  };

  const tryAddProduct = async (product: ProductSummary) => {
    if (!product.variation_id || !product.in_stock) return;
    try {
      if (product.has_options) {
        const { data: detail } = await fetchProduct(String(product.id), locale);
        const options = (detail.variations || [])
          .filter((v) => v.in_stock)
          .map((v) => ({ id: v.id, name: v.name, price: v.price }));
        if (options.length === 0) {
          toast.error(t("customBundle.unavailable"));
          return;
        }
        if (options.length === 1) {
          const only = options[0];
          addLine({
            key: `v:${only.id}`,
            productId: product.id,
            variationId: only.id,
            slug: product.slug,
            name: product.name,
            variationName: only.name,
            price: only.price,
            quantity: 1,
            imageUrl: product.image_url,
          });
          return;
        }
        setPicker({
          productId: product.id,
          name: product.name,
          slug: product.slug,
          imageUrl: product.image_url,
          options,
        });
        return;
      }
      addLine({
        key: `v:${product.variation_id}`,
        productId: product.id,
        variationId: product.variation_id,
        slug: product.slug,
        name: product.name,
        variationName: product.variation_name || "",
        price: Number(product.price ?? 0),
        quantity: 1,
        imageUrl: product.image_url,
      });
    } catch (e) {
      toast.error(
        e instanceof Error ? e.message : t("customBundle.loadFailed"),
      );
    }
  };

  const changeQty = (key: string, delta: number) => {
    setSelection((prev) => {
      const next: BundleLine[] = [];
      let total = 0;
      for (const row of prev) {
        if (row.key !== key) {
          total += row.quantity;
          next.push(row);
          continue;
        }
        const qty = row.quantity + delta;
        if (qty <= 0) continue;
        if (delta > 0 && total + qty > maxItems) continue;
        total += qty;
        next.push({ ...row, quantity: qty });
      }
      return next;
    });
  };

  const isInBundle = (product: ProductSummary) =>
    selection.some(
      (row) =>
        row.productId === product.id ||
        (product.variation_id != null && row.key === `v:${product.variation_id}`),
    );

  const removeOneOfProduct = (
    productId: number,
    variationId: number | null,
  ) => {
    setSelection((prev) => {
      const preferredKey = variationId ? `v:${variationId}` : null;
      const match =
        (preferredKey
          ? prev.find((row) => row.key === preferredKey)
          : undefined) || prev.find((row) => row.productId === productId);
      if (!match) return prev;
      if (match.quantity <= 1) {
        return prev.filter((row) => row.key !== match.key);
      }
      return prev.map((row) =>
        row.key === match.key
          ? { ...row, quantity: row.quantity - 1 }
          : row,
      );
    });
  };

  const scrollToBundle = () => {
    scrollRef.current?.scrollTo({ y: Math.max(0, trayOffsetY.current - 12), animated: true });
  };

  const submitBundle = async () => {
    if (selectionCount < minItems) {
      toast.error(t("customBundle.minRequired", { min: minItems }));
      return;
    }
    if (selectionCount > maxItems) {
      toast.error(t("customBundle.maxReached", { max: maxItems }));
      return;
    }
    setSubmitting(true);
    try {
      for (const row of selection) {
        const item: CartItem = {
          productId: row.productId,
          variationId: row.variationId,
          slug: row.slug || undefined,
          name: row.name,
          imageUrl: row.imageUrl,
          unitPrice: row.price,
          quantity: row.quantity,
        };
        await addItem(item);
      }
      toast.success(t("customBundle.addedToCart"));
      setSelection([]);
      router.push("/checkout");
    } catch (e) {
      toast.error(
        e instanceof ApiError
          ? e.message
          : t("customBundle.submitFailed"),
      );
    } finally {
      setSubmitting(false);
    }
  };

  const platforms = useMemo(() => meta?.platforms ?? [], [meta]);

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("customBundle.unavailable")}</Text>
        <PrimaryButton
          label={t("nav.home")}
          onPress={() => router.replace("/(tabs)")}
        />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <FormScrollView
        ref={scrollRef}
        contentContainerStyle={{ padding: 16 }}
        bottomInset={120}
      >
        <Text style={[styles.badge, { color: accent }]}>
          {t("customBundle.badge")}
        </Text>
        <Text style={styles.title}>{t("customBundle.title")}</Text>
        <Text style={styles.lead}>{t("customBundle.lead")}</Text>

        <Text style={styles.section}>{t("customBundle.choosePlatform")}</Text>
        <View style={styles.chipRow}>
          {platforms.map((p) => {
            const active = platform === p.id;
            return (
              <Pressable
                key={p.id}
                style={[styles.chip, active && { backgroundColor: accent }]}
                onPress={() => setPlatform(p.id)}
              >
                <Text style={[styles.chipText, active && styles.chipTextActive]}>
                  {p.label}
                </Text>
              </Pressable>
            );
          })}
        </View>

        {!platform ? (
          <Text style={styles.muted}>{t("customBundle.choosePlatformFirst")}</Text>
        ) : (
          <>
            <Text style={styles.section}>{t("customBundle.pickItems")}</Text>
            <View style={styles.chipRow}>
              {tabs.map((row) => {
                const active = tab === row.id;
                return (
                  <Pressable
                    key={row.id}
                    style={[styles.chip, active && { backgroundColor: accent }]}
                    onPress={() => setTab(row.id)}
                  >
                    <Text
                      style={[styles.chipText, active && styles.chipTextActive]}
                    >
                      {row.label}
                    </Text>
                  </Pressable>
                );
              })}
            </View>
            <TextInput
              style={styles.search}
              placeholder={t("customBundle.searchPlaceholder")}
              value={query}
              onChangeText={setQuery}
              onSubmitEditing={loadProducts}
              returnKeyType="search"
            />
            <View style={[styles.selectionBar, { borderColor: accent }]}>
              <Text style={styles.selectionBarCount}>
                {t("customBundle.itemCount", {
                  count: selectionCount,
                  max: maxItems,
                })}
              </Text>
              <Pressable
                style={[styles.selectionBarBtn, { backgroundColor: accent }]}
                onPress={scrollToBundle}
                accessibilityRole="button"
                accessibilityLabel={t("customBundle.viewBundle")}
              >
                <Text style={styles.selectionBarBtnText}>
                  {t("customBundle.viewBundle")}
                </Text>
                <FontAwesome name="chevron-down" size={12} color="#111" />
              </Pressable>
            </View>
            {loading ? <LoadingBlock /> : null}
            {!loading && products.length === 0 ? (
              <Text style={styles.muted}>{t("customBundle.empty")}</Text>
            ) : null}
            {products.map((product) => {
              const inBundle = isInBundle(product);
              return (
                <View key={product.id} style={styles.productRow}>
                  {product.image_url ? (
                    <Image
                      source={{ uri: product.image_url }}
                      style={styles.thumb}
                    />
                  ) : (
                    <View style={[styles.thumb, styles.thumbEmpty]} />
                  )}
                  <View style={styles.productBody}>
                    <Text style={styles.productName} numberOfLines={2}>
                      {product.name}
                    </Text>
                    <Text style={styles.price}>
                      {Number(product.price ?? 0).toFixed(2)} EGP
                    </Text>
                  </View>
                  <Pressable
                    style={[
                      styles.cartIconBtn,
                      inBundle
                        ? styles.cartIconBtnRemove
                        : { backgroundColor: accent },
                      !inBundle && !product.in_stock && styles.addBtnDisabled,
                    ]}
                    disabled={!inBundle && !product.in_stock}
                    accessibilityLabel={
                      inBundle
                        ? t("customBundle.removeOne")
                        : t("customBundle.add")
                    }
                    onPress={() => {
                      if (inBundle) {
                        removeOneOfProduct(
                          product.id,
                          product.variation_id ?? null,
                        );
                        return;
                      }
                      void tryAddProduct(product);
                    }}
                  >
                    <FontAwesome
                      name={inBundle ? "minus" : "cart-plus"}
                      size={18}
                      color={inBundle ? "#333" : "#111"}
                    />
                  </Pressable>
                </View>
              );
            })}
          </>
        )}

        <View
          onLayout={(e) => {
            trayOffsetY.current = e.nativeEvent.layout.y;
          }}
        >
          <Text style={styles.section}>{t("customBundle.yourBundle")}</Text>
          <Text style={styles.muted}>
            {t("customBundle.itemCount", {
              count: selectionCount,
              max: maxItems,
            })}{" "}
            · {selectionTotal.toFixed(2)} EGP
          </Text>
          {selection.length === 0 ? (
            <Text style={styles.muted}>{t("customBundle.trayEmpty")}</Text>
          ) : (
            selection.map((row) => (
              <View key={row.key} style={styles.productRow}>
                <View style={styles.productBody}>
                  <Text style={styles.productName}>{row.name}</Text>
                  {row.variationName ? (
                    <Text style={styles.muted}>{row.variationName}</Text>
                  ) : null}
                  <Text style={styles.price}>
                    {(row.price * row.quantity).toFixed(2)} EGP
                  </Text>
                </View>
                <View style={styles.qtyRow}>
                  <Pressable
                    style={styles.qtyBtn}
                    onPress={() => changeQty(row.key, -1)}
                  >
                    <FontAwesome name="minus" size={14} color="#333" />
                  </Pressable>
                  <Text style={styles.qtyValue}>{row.quantity}</Text>
                  <Pressable
                    style={styles.qtyBtn}
                    onPress={() => changeQty(row.key, 1)}
                  >
                    <FontAwesome name="plus" size={14} color="#333" />
                  </Pressable>
                </View>
              </View>
            ))
          )}

          <PrimaryButton
            label={
              submitting
                ? t("common.loading")
                : t("customBundle.checkout")
            }
            disabled={submitting || selectionCount < minItems}
            onPress={() => void submitBundle()}
          />
        </View>
      </FormScrollView>

      <Modal visible={!!picker} transparent animationType="fade">
        <View style={styles.modalBackdrop}>
          <View style={styles.modalCard}>
            <Text style={styles.section}>{t("customBundle.chooseVariation")}</Text>
            <Text style={styles.productName}>{picker?.name}</Text>
            <FlatList
              data={picker?.options || []}
              keyExtractor={(item) => String(item.id)}
              renderItem={({ item }) => (
                <Pressable
                  style={styles.optionRow}
                  onPress={() => {
                    if (!picker) return;
                    addLine({
                      key: `v:${item.id}`,
                      productId: picker.productId,
                      variationId: item.id,
                      slug: picker.slug,
                      name: picker.name,
                      variationName: item.name,
                      price: item.price,
                      quantity: 1,
                      imageUrl: picker.imageUrl,
                    });
                    setPicker(null);
                  }}
                >
                  <Text style={styles.productName}>{item.name}</Text>
                  <Text style={styles.price}>{item.price.toFixed(2)} EGP</Text>
                </Pressable>
              )}
            />
            <PrimaryButton
              label={t("common.cancel")}
              onPress={() => setPicker(null)}
            />
          </View>
        </View>
      </Modal>
    </Screen>
  );
}

const styles = StyleSheet.create({
  badge: {
    fontSize: 12,
    fontWeight: "800",
    letterSpacing: 0.5,
    textTransform: "uppercase",
    marginBottom: 6,
  },
  title: { fontSize: 22, fontWeight: "800", color: "#111", marginBottom: 8 },
  lead: { fontSize: 14, color: "#555", lineHeight: 20, marginBottom: 16 },
  section: {
    fontSize: 16,
    fontWeight: "800",
    color: "#111",
    marginTop: 12,
    marginBottom: 8,
  },
  muted: { color: "#777", marginBottom: 10 },
  chipRow: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 12 },
  chip: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
    backgroundColor: "#e8e8e8",
  },
  chipText: { fontWeight: "700", color: "#333" },
  chipTextActive: { color: "#111" },
  search: {
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#ccc",
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    marginBottom: 10,
    backgroundColor: "#fff",
  },
  selectionBar: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    marginBottom: 12,
    borderRadius: 10,
    borderWidth: 1,
    backgroundColor: "#fff",
  },
  selectionBarCount: { flex: 1, fontWeight: "700", color: "#222" },
  selectionBarBtn: {
    flexDirection: "row",
    alignItems: "center",
    gap: 6,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 8,
  },
  selectionBarBtnText: { fontWeight: "800", color: "#111" },
  productRow: {
    flexDirection: "row",
    alignItems: "center",
    gap: 10,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#e8e8e8",
  },
  thumb: { width: 56, height: 56, borderRadius: 8, backgroundColor: "#eee" },
  thumbEmpty: { backgroundColor: "#ddd" },
  productBody: { flex: 1 },
  productName: { fontWeight: "700", color: "#222" },
  price: { color: "#444", marginTop: 2 },
  cartIconBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    alignItems: "center",
    justifyContent: "center",
  },
  cartIconBtnRemove: {
    backgroundColor: "#fff",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#ccc",
  },
  addBtnDisabled: { opacity: 0.4 },
  qtyRow: { flexDirection: "row", alignItems: "center", gap: 8 },
  qtyBtn: {
    width: 32,
    height: 32,
    borderRadius: 8,
    backgroundColor: "#e8e8e8",
    alignItems: "center",
    justifyContent: "center",
  },
  qtyValue: { minWidth: 20, textAlign: "center", fontWeight: "700" },
  modalBackdrop: {
    flex: 1,
    backgroundColor: "rgba(0,0,0,0.45)",
    justifyContent: "flex-end",
  },
  modalCard: {
    backgroundColor: "#F7F7F5",
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    padding: 16,
    maxHeight: "70%",
  },
  optionRow: {
    paddingVertical: 12,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: "#e0e0e0",
  },
});
