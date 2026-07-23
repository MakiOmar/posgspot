import { Text, View } from "react-native";
import { useApp } from "../src/contexts/AppContext";
import { Screen } from "../src/components/ui";

const FAQ_EN = [
  {
    q: "How do I track my order?",
    a: "Open Account → Orders. Tracking appears when the order is shipped.",
  },
  {
    q: "Do you offer cash on delivery?",
    a: "Yes, when COD is enabled in store settings.",
  },
  {
    q: "Can I pay with Fawry?",
    a: "Yes, when online payments are enabled. Use the Fawry option at checkout.",
  },
];

const FAQ_AR = [
  {
    q: "كيف أتتبع طلبي؟",
    a: "من حسابي ← الطلبات. يظهر التتبع عند الشحن.",
  },
  {
    q: "هل يوجد دفع عند الاستلام؟",
    a: "نعم عند تفعيل الخيار من إعدادات المتجر.",
  },
  {
    q: "هل يمكن الدفع عبر فوري؟",
    a: "نعم عند تفعيل الدفع الإلكتروني واختيار فوري عند إتمام الشراء.",
  },
];

export default function FaqScreen() {
  const { locale } = useApp();
  const items = locale === "ar" ? FAQ_AR : FAQ_EN;
  return (
    <Screen>
      {items.map((item) => (
        <View key={item.q} style={{ marginBottom: 16 }}>
          <Text style={{ fontWeight: "800", marginBottom: 4 }}>{item.q}</Text>
          <Text style={{ lineHeight: 20 }}>{item.a}</Text>
        </View>
      ))}
    </Screen>
  );
}
