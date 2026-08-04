import { Pressable, ScrollView, StyleSheet, Text, View } from "react-native";
import { useRouter } from "expo-router";
import { absoluteMediaUrl } from "../../lib/storefront-href";
import type { Brand, Category, ProductSummary } from "../../lib/types";
import { useApp } from "../../contexts/AppContext";
import { RemoteImage } from "../RemoteImage";
import { ProductCard } from "../ui";

export function ProductRail({
  title,
  products,
}: {
  title: string;
  products: ProductSummary[];
}) {
  if (!products.length) return null;
  return (
    <View style={styles.sectionBlock}>
      <Text style={styles.sectionTitle}>{title}</Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {products.map((item) => (
          <View key={String(item.id)} style={styles.railCard}>
            <ProductCard product={item} wide />
          </View>
        ))}
      </ScrollView>
    </View>
  );
}

export function CategoryRail({ categories }: { categories: Category[] }) {
  const { t } = useApp();
  const router = useRouter();
  if (!categories.length) return null;
  return (
    <View style={styles.sectionBlock}>
      <Text style={styles.sectionTitle}>{t("home.topCategories")}</Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {categories.map((cat) => {
          const image = absoluteMediaUrl(cat.image_url);
          return (
            <Pressable
              key={cat.id}
              style={styles.catCard}
              onPress={() => router.push(`/category/${cat.slug}` as never)}
            >
              <RemoteImage uri={image} style={styles.catImage} />
              <Text numberOfLines={2} style={styles.catName}>
                {cat.name}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

export function BrandRail({ brands }: { brands: Brand[] }) {
  const { t } = useApp();
  const router = useRouter();
  if (!brands.length) return null;
  return (
    <View style={styles.sectionBlock}>
      <Text style={styles.sectionTitle}>{t("common.brands")}</Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.rail}
      >
        {brands.map((brand) => {
          const image = absoluteMediaUrl(brand.image_url);
          return (
            <Pressable
              key={brand.id}
              style={styles.brandCard}
              onPress={() => router.push(`/brands/${brand.slug}` as never)}
            >
              {image ? (
                <RemoteImage
                  uri={image}
                  style={styles.brandImage}
                  contentFit="contain"
                />
              ) : (
                <Text style={styles.brandFallback} numberOfLines={2}>
                  {brand.name}
                </Text>
              )}
            </Pressable>
          );
        })}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  sectionBlock: { marginBottom: 20, paddingHorizontal: 16 },
  sectionTitle: {
    fontSize: 18,
    fontWeight: "800",
    marginBottom: 12,
    color: "#111",
    flexShrink: 1,
  },
  rail: { gap: 10, paddingRight: 8 },
  railCard: { width: 160 },
  catCard: { width: 110, marginRight: 4 },
  catImage: {
    width: 110,
    height: 110,
    borderRadius: 12,
    backgroundColor: "#eee",
  },
  catImagePlaceholder: {
    width: 110,
    height: 110,
    borderRadius: 12,
    backgroundColor: "#e8e8e8",
  },
  catName: {
    marginTop: 6,
    fontSize: 13,
    fontWeight: "600",
    textAlign: "center",
  },
  brandCard: {
    width: 100,
    height: 72,
    borderRadius: 12,
    backgroundColor: "#fff",
    alignItems: "center",
    justifyContent: "center",
    padding: 8,
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "#e5e5e5",
  },
  brandImage: { width: 84, height: 48 },
  brandFallback: { fontSize: 12, fontWeight: "600", textAlign: "center" },
});

/** Shared rail / section chrome used by home blocks (e.g. category shelf). */
export const homeRailStyles = {
  sectionBlock: styles.sectionBlock,
  sectionTitle: styles.sectionTitle,
  rail: styles.rail,
  railCard: styles.railCard,
};
