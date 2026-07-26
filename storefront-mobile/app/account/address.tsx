import { useCallback, useEffect, useState } from "react";
import { ScrollView } from "react-native";
import { Redirect, Stack } from "expo-router";
import { fetchProfile, updateAddress } from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { HeaderCartButton } from "../../src/components/account/HeaderCartButton";
import { LabeledInput } from "../../src/components/LabeledInput";
import {
  ErrorBlock,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { toast } from "../../src/lib/toast";

export default function AddressScreen() {
  const { token, t, updateContactLocal } = useApp();
  const [line1, setLine1] = useState("");
  const [line2, setLine2] = useState("");
  const [city, setCity] = useState("");
  const [state, setState] = useState("");
  const [country, setCountry] = useState("EG");
  const [zip, setZip] = useState("");
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) return;
    setLoading(true);
    try {
      const { data } = await fetchProfile(token);
      setLine1(data.address_line_1 || "");
      setLine2(data.address_line_2 || "");
      setCity(data.city || "");
      setState(data.state || "");
      setCountry(data.country || "EG");
      setZip(data.zip_code || "");
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [token, t]);

  useEffect(() => {
    void load();
  }, [load]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  if (loading) {
    return (
      <Screen>
        <Stack.Screen
          options={{
            title: t("account.addressTitle"),
            headerRight: () => <HeaderCartButton />,
          }}
        />
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen padded={false}>
      <Stack.Screen
        options={{
          title: t("account.addressTitle"),
          headerRight: () => <HeaderCartButton />,
        }}
      />
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
        {error ? (
          <ErrorBlock message={error} onRetry={() => void load()} />
        ) : null}
        <LabeledInput
          label={t("account.addressLine1")}
          value={line1}
          onChangeText={setLine1}
        />
        <LabeledInput
          label={t("account.addressLine2")}
          value={line2}
          onChangeText={setLine2}
        />
        <LabeledInput
          label={t("checkout.city")}
          value={city}
          onChangeText={setCity}
        />
        <LabeledInput
          label={t("checkout.state")}
          value={state}
          onChangeText={setState}
        />
        <LabeledInput
          label={t("checkout.country")}
          value={country}
          onChangeText={setCountry}
          autoCapitalize="characters"
        />
        <LabeledInput
          label={t("account.zip")}
          value={zip}
          onChangeText={setZip}
        />
        <PrimaryButton
          label={busy ? t("common.loading") : t("account.saveAddress")}
          disabled={busy}
          onPress={() => {
            setBusy(true);
            void updateAddress(token, {
              address_line_1: line1.trim(),
              address_line_2: line2.trim() || null,
              city: city.trim(),
              state: state.trim(),
              country: country.trim() || "EG",
              zip_code: zip.trim() || null,
            })
              .then(async ({ data }) => {
                await updateContactLocal(data);
                toast.success(t("account.addressSaved"));
              })
              .catch((e) =>
                toast.error(
                  e instanceof Error ? e.message : t("common.error"),
                ),
              )
              .finally(() => setBusy(false));
          }}
        />
      </ScrollView>
    </Screen>
  );
}
