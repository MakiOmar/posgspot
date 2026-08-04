import { useLocalSearchParams } from "expo-router";
import { useCallback, useEffect, useState } from "react";
import { fetchCategory } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { ProductGridScreen } from "../../src/components/catalog/ProductGridScreen";
import { Screen } from "../../src/components/ui";
import { paramString } from "../../src/lib/product-path";

export default function CategoryScreen() {
  const params = useLocalSearchParams<{ slug: string | string[] }>();
  const slug = paramString(params.slug) || "";
  const { locale } = useApp();
  const [title, setTitle] = useState(slug);

  const loadTitle = useCallback(async () => {
    if (!slug) return;
    try {
      const cat = await fetchCategory(slug, locale);
      setTitle(cat.data.name || slug);
    } catch {
      setTitle(slug);
    }
  }, [slug, locale]);

  useEffect(() => {
    void loadTitle();
  }, [loadTitle]);

  return (
    <Screen padded={false}>
      <ProductGridScreen
        locale={locale}
        categorySlug={slug || undefined}
        title={title}
      />
    </Screen>
  );
}
