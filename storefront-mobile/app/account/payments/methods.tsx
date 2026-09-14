import { ScrollView, StyleSheet, Text, View } from "react-native";
import { Redirect } from "expo-router";
import { Ionicons } from "@expo/vector-icons";
import { useApp } from "../../../src/contexts/AppContext";
import { PrimaryButton, Screen } from "../../../src/components/ui";
import { toast } from "../../../src/lib/toast";
import { useRtl } from "../../../src/lib/rtl";

export default function PaymentMethodsScreen() {
  const { token, t, accent } = useApp();
  const { textAlign, writingDirection } = useRtl();

  if (!token) {
    return <Redirect href="/login" />;
  }

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <View style={styles.empty}>
          <Ionicons name="card-outline" size={48} color={accent} />
          <Text style={[styles.title, { textAlign, writingDirection }]}>
            {t("account.paymentMethods")}
          </Text>
          <Text style={[styles.hint, { textAlign, writingDirection }]}>
            {t("account.paymentMethodsEmpty")}
          </Text>
          <PrimaryButton
            label={t("account.addPaymentMethod")}
            onPress={() => toast.info(t("account.paymentMethodsSoon"))}
          />
        </View>
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 24, paddingBottom: 48 },
  empty: { alignItems: "center", gap: 12, marginTop: 32 },
  title: { fontSize: 18, fontWeight: "800", color: "#111" },
  hint: { color: "#888", lineHeight: 20, marginBottom: 8 },
});
