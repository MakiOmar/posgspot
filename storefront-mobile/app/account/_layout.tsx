import { Stack } from "expo-router";
import { HeaderBackButton } from "../../src/components/account/HeaderBackButton";
import { HeaderCartButton } from "../../src/components/account/HeaderCartButton";
import { useApp } from "../../src/contexts/AppContext";

/**
 * Shared account stack chrome: back + cart; titles set per-screen.
 */
export default function AccountLayout() {
  const { t } = useApp();

  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: "#F7F7F5" },
        headerShadowVisible: false,
        headerTintColor: "#111",
        headerLeft: () => <HeaderBackButton />,
        headerRight: () => <HeaderCartButton />,
      }}
    >
      <Stack.Screen name="profile" options={{ title: t("account.personalInfo") }} />
      <Stack.Screen name="security" options={{ title: t("account.loginSecurity") }} />
      <Stack.Screen name="password" options={{ title: t("account.loginSecurity") }} />
      <Stack.Screen name="payments/index" options={{ title: t("account.paymentsPayouts") }} />
      <Stack.Screen name="payments/methods" options={{ title: t("account.paymentMethods") }} />
      <Stack.Screen name="payments/list" options={{ title: t("account.paymentsTab") }} />
      <Stack.Screen name="payments/credits" options={{ title: t("account.creditsCoupons") }} />
      <Stack.Screen name="address" options={{ title: t("account.addressTitle") }} />
      <Stack.Screen name="rewards" options={{ title: t("rewards.title") }} />
      <Stack.Screen name="orders/index" options={{ title: t("account.orders") }} />
      <Stack.Screen name="orders/[id]" options={{ title: t("account.orders") }} />
      <Stack.Screen name="invoice" options={{ title: t("account.invoice") }} />
    </Stack>
  );
}
