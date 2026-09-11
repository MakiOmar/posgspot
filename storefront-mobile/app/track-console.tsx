import { useCallback, useEffect, useState } from "react";
import { StyleSheet, Text, View } from "react-native";
import { Link } from "expo-router";
import { fetchAccountDeviceServices, trackDevice } from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { LabeledInput } from "../src/components/LabeledInput";
import {
  FormScrollView,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../src/components/ui";

type DeviceService = {
  id?: number | null;
  tracking_code?: string;
  status?: string;
  status_display?: string;
  device_serial_number?: string;
  notes?: string | null;
  submitted_at?: string | null;
  status_updated_at?: string | null;
  device_model?: {
    full_name?: string;
    name?: string;
    brand?: string;
  } | null;
  store_profile?: { name?: string } | null;
};

function ServiceCard({
  service,
  t,
}: {
  service: DeviceService;
  t: (key: string) => string;
}) {
  const device =
    service.device_model?.full_name ||
    service.device_model?.name ||
    [service.device_model?.brand, service.device_model?.name]
      .filter(Boolean)
      .join(" ");

  return (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>
        {service.tracking_code || t("trackConsole.result")}
      </Text>
      {service.status_display || service.status ? (
        <Text>
          {t("trackConsole.status")}:{" "}
          {service.status_display || service.status}
        </Text>
      ) : null}
      {device ? (
        <Text>
          {t("trackConsole.device")}: {device}
        </Text>
      ) : null}
      {service.device_serial_number ? (
        <Text>
          {t("trackConsole.serialNo")}: {service.device_serial_number}
        </Text>
      ) : null}
      {service.store_profile?.name ? (
        <Text>
          {t("trackConsole.store")}: {service.store_profile.name}
        </Text>
      ) : null}
      {service.status_updated_at ? (
        <Text>
          {t("trackConsole.updated")}: {service.status_updated_at}
        </Text>
      ) : null}
      {service.notes ? <Text>{service.notes}</Text> : null}
    </View>
  );
}

export default function TrackConsoleScreen() {
  const { t, accent, token } = useApp();
  const [phone, setPhone] = useState("");
  const [services, setServices] = useState<DeviceService[]>([]);
  const [myServices, setMyServices] = useState<DeviceService[]>([]);
  const [myLoaded, setMyLoaded] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const loadMine = useCallback(() => {
    if (!token) {
      setMyServices([]);
      setMyLoaded(true);
      return;
    }
    setMyLoaded(false);
    void fetchAccountDeviceServices(token)
      .then(({ data }) => {
        setMyServices(
          ((data as { services?: DeviceService[] })?.services ||
            []) as DeviceService[],
        );
      })
      .catch(() => setMyServices([]))
      .finally(() => setMyLoaded(true));
  }, [token]);

  useEffect(() => {
    loadMine();
  }, [loadMine]);

  // Logged-in: auto-list only. Guests: phone form only.
  if (token) {
    return (
      <Screen padded={false} avoidKeyboard={false}>
        <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
          <Text style={styles.sectionTitle}>{t("trackConsole.myServices")}</Text>
          {!myLoaded ? (
            <LoadingBlock />
          ) : myServices.length === 0 ? (
            <Text style={styles.message}>
              {t("trackConsole.myServicesEmpty")}
            </Text>
          ) : (
            myServices.map((service, index) => (
              <ServiceCard
                key={`mine-${service.tracking_code || service.id || index}`}
                service={service}
                t={t}
              />
            ))
          )}
        </FormScrollView>
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={styles.lead}>{t("trackConsole.guestIntro")}</Text>
        <Text style={styles.signInHint}>
          {t("trackConsole.myServicesSignIn")}{" "}
          <Link href="/login" style={{ color: accent, fontWeight: "700" }}>
            {t("auth.signIn")}
          </Link>
        </Text>

        <Text style={styles.sectionTitle}>{t("trackConsole.lookup")}</Text>
        <LabeledInput
          label={t("trackConsole.phone")}
          value={phone}
          onChangeText={setPhone}
          placeholder={t("trackConsole.phonePlaceholder")}
          keyboardType="phone-pad"
          autoComplete="tel"
        />
        <PrimaryButton
          label={busy ? t("common.loading") : t("trackConsole.search")}
          disabled={busy || !phone.trim()}
          onPress={() => {
            setBusy(true);
            setMessage(null);
            void trackDevice({ phone_number: phone.trim() })
              .then(({ data }) => {
                const list =
                  (data as { services?: DeviceService[] })?.services || [];
                setServices(list);
                if (!list.length) {
                  setMessage(t("trackConsole.notFound"));
                }
              })
              .catch((e) =>
                setMessage(e instanceof Error ? e.message : t("common.error")),
              )
              .finally(() => setBusy(false));
          }}
        />
        {message ? <Text style={styles.message}>{message}</Text> : null}
        {services.map((service, index) => (
          <ServiceCard
            key={`search-${service.tracking_code || service.id || index}`}
            service={service}
            t={t}
          />
        ))}
      </FormScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  lead: { color: "#555", lineHeight: 20, marginBottom: 8 },
  signInHint: { color: "#666", marginBottom: 16, lineHeight: 20 },
  sectionTitle: { fontSize: 17, fontWeight: "800", marginBottom: 10 },
  message: { marginTop: 8, marginBottom: 8, color: "#666", lineHeight: 20 },
  card: {
    backgroundColor: "#fff",
    borderRadius: 12,
    padding: 14,
    marginTop: 12,
    gap: 4,
  },
  cardTitle: { fontWeight: "800", fontSize: 16, marginBottom: 4 },
});
