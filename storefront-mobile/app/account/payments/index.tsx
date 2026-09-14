import { ScrollView, StyleSheet } from "react-native";
import { Redirect, useRouter, type Href } from "expo-router";
import { useApp } from "../../../src/contexts/AppContext";
import { AccountMenuRow } from "../../../src/components/account/AccountMenuRow";
import { Screen } from "../../../src/components/ui";

export default function PaymentsMenuScreen() {
  const { token, t } = useApp();
  const router = useRouter();

  if (!token) {
    return <Redirect href="/login" />;
  }

  return (
    <Screen padded={false}>
      <ScrollView contentContainerStyle={styles.pad}>
        <AccountMenuRow
          icon="card-outline"
          label={t("account.paymentMethods")}
          onPress={() =>
            router.push("/account/payments/methods" as unknown as Href)
          }
        />
        <AccountMenuRow
          icon="receipt-outline"
          label={t("account.paymentsTab")}
          onPress={() =>
            router.push("/account/payments/list" as unknown as Href)
          }
        />
        <AccountMenuRow
          icon="ticket-outline"
          label={t("account.creditsCoupons")}
          onPress={() =>
            router.push("/account/payments/credits" as unknown as Href)
          }
        />
      </ScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { paddingHorizontal: 16, paddingBottom: 48 },
});
