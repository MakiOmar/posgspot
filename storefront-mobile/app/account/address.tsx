import { useCallback, useEffect, useState } from "react";
import { Redirect, Stack } from "expo-router";
import {
  fetchGeoCountries,
  fetchGeoStates,
  fetchProfile,
  updateAddress,
} from "../../src/lib/api";
import type { GeoCountry, GeoState } from "../../src/lib/types";
import { useApp } from "../../src/contexts/AppContext";
import { HeaderBackButton } from "../../src/components/account/HeaderBackButton";
import { HeaderCartButton } from "../../src/components/account/HeaderCartButton";
import { LabeledInput } from "../../src/components/LabeledInput";
import { SelectField } from "../../src/components/SelectField";
import {
  ErrorBlock,
  FormScrollView,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { toast } from "../../src/lib/toast";

function normalizeCountry(code: string | undefined | null): string {
  const raw = (code || "").trim().toUpperCase();
  if (!raw || raw === "EGYPT" || raw === "EGY") return "EG";
  return raw.slice(0, 2);
}

export default function AddressScreen() {
  const { token, t, updateContactLocal } = useApp();
  const [line1, setLine1] = useState("");
  const [line2, setLine2] = useState("");
  const [city, setCity] = useState("");
  const [stateCode, setStateCode] = useState("");
  const [stateText, setStateText] = useState("");
  const [country, setCountry] = useState("EG");
  const [zip, setZip] = useState("");
  const [countries, setCountries] = useState<GeoCountry[]>([]);
  const [states, setStates] = useState<GeoState[]>([]);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const useStateSelect = states.length > 0;
  const effectiveState = useStateSelect ? stateCode : stateText;

  const load = useCallback(async () => {
    if (!token) {
      setLoading(false);
      return;
    }
    setLoading(true);
    try {
      const [{ data }, geo] = await Promise.all([
        fetchProfile(token),
        fetchGeoCountries().catch(() => ({ data: [{ code: "EG", name: "Egypt" }] })),
      ]);
      setCountries(geo.data || [{ code: "EG", name: "Egypt" }]);
      setLine1(data.address_line_1 || "");
      setLine2(data.address_line_2 || "");
      setCity(data.city || "");
      setCountry(normalizeCountry(data.country) || "EG");
      setStateCode(data.state || "");
      setStateText(data.state || "");
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

  useEffect(() => {
    const code = normalizeCountry(country);
    if (!code) return;
    void fetchGeoStates(code)
      .then(({ data }) => {
        const list = data || [];
        setStates(list);
        if (list.length && stateCode && !list.some((s) => s.code === stateCode)) {
          setStateCode("");
        }
      })
      .catch(() => setStates([]));
  }, [country]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  const headerOpts = {
    title: t("account.addressTitle"),
    headerLeft: () => <HeaderBackButton />,
    headerRight: () => <HeaderCartButton />,
  };

  if (loading) {
    return (
      <Screen>
        <Stack.Screen options={headerOpts} />
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <Stack.Screen options={headerOpts} />
      <FormScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
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
        <SelectField
          label={t("checkout.country")}
          value={country}
          options={countries.map((c) => ({ value: c.code, label: c.name }))}
          onChange={(code) => {
            setCountry(normalizeCountry(code));
            setStateCode("");
            setStateText("");
          }}
        />
        {useStateSelect ? (
          <SelectField
            label={t("checkout.state")}
            value={stateCode}
            options={states.map((s) => ({ value: s.code, label: s.name }))}
            onChange={(code) => setStateCode(code)}
            placeholder={t("checkout.selectState")}
          />
        ) : (
          <LabeledInput
            label={t("checkout.state")}
            value={stateText}
            onChangeText={setStateText}
            placeholder={t("checkout.statePlaceholder")}
          />
        )}
        <LabeledInput
          label={t("checkout.city")}
          value={city}
          onChangeText={setCity}
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
              state: effectiveState.trim(),
              country: normalizeCountry(country) || "EG",
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
      </FormScrollView>
    </Screen>
  );
}
