import { useEffect, useMemo, useState } from "react";
import {
  Image,
  Pressable,
  StyleSheet,
  Switch,
  Text,
  View,
} from "react-native";
import { Link, useRouter } from "expo-router";
import * as ImagePicker from "expo-image-picker";
import {
  ApiError,
  appendSellToUsPhoto,
  fetchSellToUsMeta,
  submitSellToUsRequest,
  verifySellToUsInvoice,
} from "../src/lib/api";
import { useApp } from "../src/contexts/AppContext";
import { toast } from "../src/lib/toast";
import { LabeledInput } from "../src/components/LabeledInput";
import { SelectField } from "../src/components/SelectField";
import {
  FormScrollView,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../src/components/ui";
import type { SellToUsMeta, SellToUsType } from "../src/lib/types";

type PhotoAsset = { uri: string; fileName: string };

export default function SellToUsScreen() {
  const { t, settings, token, contact, accent, locale } = useApp();
  const router = useRouter();
  const enabled = settings?.sell_to_us?.enabled === true;

  const [meta, setMeta] = useState<SellToUsMeta | null>(null);
  const [loadingMeta, setLoadingMeta] = useState(true);
  const [type, setType] = useState<SellToUsType | "">("");
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [city, setCity] = useState("");
  const [notes, setNotes] = useState("");
  const [purchasedFromUs, setPurchasedFromUs] = useState(false);
  const [invoiceNo, setInvoiceNo] = useState("");
  const [verifiedTxnId, setVerifiedTxnId] = useState<number | null>(null);
  const [invoiceVerified, setInvoiceVerified] = useState(false);
  const [verifying, setVerifying] = useState(false);
  const [accountNote, setAccountNote] = useState("");
  const [gameTitle, setGameTitle] = useState("");
  const [platform, setPlatform] = useState("");
  const [condition, setCondition] = useState("");
  const [model, setModel] = useState("");
  const [storage, setStorage] = useState("");
  const [photos, setPhotos] = useState<PhotoAsset[]>([]);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (!enabled) {
      setLoadingMeta(false);
      return;
    }
    setLoadingMeta(true);
    void fetchSellToUsMeta(locale)
      .then(({ data }) => setMeta(data))
      .catch(() => {
        setMeta(null);
        toast.error(t("sellToUs.loadFailed"));
      })
      .finally(() => setLoadingMeta(false));
  }, [enabled, locale, t]);

  useEffect(() => {
    if (!contact) return;
    if (!name) setName(contact.name || "");
    if (!email) setEmail(contact.email || "");
    if (!phone && contact.mobile) setPhone(contact.mobile);
  }, [contact, name, email, phone]);

  const maxPhotos = meta?.max_photos ?? 6;
  const maxPhotoKb = meta?.max_photo_kb ?? 4096;
  const requireVerify = Boolean(
    meta?.purchased_from_us?.verify_required_when_yes,
  );

  const typeOptions = useMemo(
    () =>
      (meta?.types || []).map((row) => ({
        value: row.id,
        label: row.label,
      })),
    [meta],
  );

  if (!enabled) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("sellToUs.unavailable")}</Text>
        <PrimaryButton
          label={t("nav.home")}
          onPress={() => router.replace("/(tabs)")}
        />
      </Screen>
    );
  }

  if (!token) {
    return (
      <Screen padded={false}>
        <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
          <Text style={[styles.badge, { color: accent }]}>
            {t("sellToUs.gateBadge")}
          </Text>
          <Text style={styles.title}>{t("sellToUs.gateTitle")}</Text>
          <Text style={styles.lead}>{t("sellToUs.gateLead")}</Text>
          <PrimaryButton
            label={t("sellToUs.loginCta")}
            onPress={() =>
              router.push({
                pathname: "/login",
                params: { next: "/sell-to-us" },
              })
            }
          />
          <Text style={styles.muted}>
            {t("sellToUs.gateRegisterBefore")}{" "}
            <Link
              href={{ pathname: "/register", params: { next: "/sell-to-us" } }}
              style={{ color: accent }}
            >
              {t("sellToUs.gateRegisterLink")}
            </Link>{" "}
            {t("sellToUs.gateRegisterAfter")}
          </Text>
        </FormScrollView>
      </Screen>
    );
  }

  if (loadingMeta) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  if (!meta) {
    return (
      <Screen>
        <Text style={styles.muted}>{t("sellToUs.loadFailed")}</Text>
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={{ padding: 16 }} bottomInset={64}>
        <Text style={[styles.badge, { color: accent }]}>
          {t("sellToUs.badge")}
        </Text>
        <Text style={styles.title}>{t("sellToUs.title")}</Text>
        <Text style={styles.lead}>{t("sellToUs.lead")}</Text>

        <SelectField
          label={t("sellToUs.chooseType")}
          value={type}
          options={typeOptions}
          onChange={(value) => setType(value as SellToUsType | "")}
        />

        {!type ? null : (
          <>
            <LabeledInput
              label={t("sellToUs.name")}
              value={name}
              onChangeText={setName}
            />
            <LabeledInput
              label={t("sellToUs.email")}
              value={email}
              onChangeText={setEmail}
              autoCapitalize="none"
              keyboardType="email-address"
            />
            <LabeledInput
              label={t("sellToUs.phone")}
              value={phone}
              onChangeText={setPhone}
              keyboardType="phone-pad"
            />
            <SelectField
              label={t("sellToUs.city")}
              value={city}
              placeholder={t("sellToUs.selectCity")}
              options={(meta.cities || []).map((c) => ({
                value: c.id,
                label: c.label,
              }))}
              onChange={(value) => setCity(value)}
            />
            <LabeledInput
              label={t("sellToUs.notes")}
              value={notes}
              onChangeText={setNotes}
              multiline
              style={{ height: 90, textAlignVertical: "top" }}
              placeholder={t("sellToUs.notesPlaceholder")}
            />

            <View style={styles.switchRow}>
              <Text style={styles.switchLabel}>{t("sellToUs.purchasedFromUs")}</Text>
              <Switch
                value={purchasedFromUs}
                onValueChange={(yes) => {
                  setPurchasedFromUs(yes);
                  setInvoiceVerified(false);
                  setVerifiedTxnId(null);
                }}
              />
            </View>
            {purchasedFromUs ? (
              <View style={styles.verifyRow}>
                <View style={{ flex: 1 }}>
                  <LabeledInput
                    label={t("sellToUs.invoiceNo")}
                    value={invoiceNo}
                    onChangeText={(v) => {
                      setInvoiceNo(v);
                      setInvoiceVerified(false);
                      setVerifiedTxnId(null);
                    }}
                    autoCapitalize="none"
                  />
                </View>
                <PrimaryButton
                  label={verifying ? t("common.loading") : t("sellToUs.verify")}
                  disabled={verifying || !invoiceNo.trim()}
                  onPress={() => {
                    setVerifying(true);
                    void verifySellToUsInvoice(token, invoiceNo.trim())
                      .then(({ data }) => {
                        if (data.valid && data.order) {
                          setInvoiceVerified(true);
                          setVerifiedTxnId(data.order.id);
                          toast.success(t("sellToUs.verified"));
                        } else {
                          setInvoiceVerified(false);
                          setVerifiedTxnId(null);
                          toast.error(t("sellToUs.verifyFailed"));
                        }
                      })
                      .catch((e) =>
                        toast.error(
                          e instanceof ApiError
                            ? e.message
                            : t("sellToUs.verifyFailed"),
                        ),
                      )
                      .finally(() => setVerifying(false));
                  }}
                />
              </View>
            ) : null}
            {invoiceVerified ? (
              <Text style={[styles.ok, { color: accent }]}>
                {t("sellToUs.verified")}
              </Text>
            ) : null}

            {type === "account" ? (
              <LabeledInput
                label={t("sellToUs.accountNote")}
                value={accountNote}
                onChangeText={setAccountNote}
                multiline
                style={{ height: 90, textAlignVertical: "top" }}
                placeholder={t("sellToUs.accountNotePlaceholder")}
              />
            ) : null}

            {type === "disc" ? (
              <>
                <LabeledInput
                  label={t("sellToUs.gameTitle")}
                  value={gameTitle}
                  onChangeText={setGameTitle}
                />
                <SelectField
                  label={t("sellToUs.platform")}
                  value={platform}
                  options={(meta.platforms || []).map((p) => ({
                    value: p.id,
                    label: p.label,
                  }))}
                  onChange={setPlatform}
                />
                <SelectField
                  label={t("sellToUs.condition")}
                  value={condition}
                  options={(meta.conditions || []).map((c) => ({
                    value: c.id,
                    label: c.label,
                  }))}
                  onChange={setCondition}
                />
              </>
            ) : null}

            {type === "device" ? (
              <>
                <SelectField
                  label={t("sellToUs.model")}
                  value={model}
                  options={(meta.device_models || []).map((m) => ({
                    value: m.id,
                    label: m.label,
                  }))}
                  onChange={setModel}
                />
                <SelectField
                  label={t("sellToUs.storage")}
                  value={storage}
                  options={(meta.storage_options || []).map((s) => ({
                    value: s.id,
                    label: s.label,
                  }))}
                  onChange={setStorage}
                />
                <SelectField
                  label={t("sellToUs.condition")}
                  value={condition}
                  options={(meta.conditions || []).map((c) => ({
                    value: c.id,
                    label: c.label,
                  }))}
                  onChange={setCondition}
                />
                <Text style={styles.section}>{t("sellToUs.photos")}</Text>
                <Text style={styles.muted}>
                  {t("sellToUs.photosHint", {
                    max: maxPhotos,
                    kb: maxPhotoKb,
                  })}
                </Text>
                <View style={styles.photoRow}>
                  {photos.map((photo) => (
                    <Pressable
                      key={photo.uri}
                      onLongPress={() =>
                        setPhotos((prev) =>
                          prev.filter((p) => p.uri !== photo.uri),
                        )
                      }
                    >
                      <Image source={{ uri: photo.uri }} style={styles.photo} />
                    </Pressable>
                  ))}
                  {photos.length < maxPhotos ? (
                    <Pressable
                      style={styles.photoAdd}
                      onPress={() => {
                        void (async () => {
                          const permission =
                            await ImagePicker.requestMediaLibraryPermissionsAsync();
                          if (!permission.granted) {
                            toast.error(t("sellToUs.photoPermission"));
                            return;
                          }
                          const picked =
                            await ImagePicker.launchImageLibraryAsync({
                              mediaTypes: ["images"],
                              quality: 0.85,
                              allowsMultipleSelection: true,
                              selectionLimit: maxPhotos - photos.length,
                            });
                          if (picked.canceled) return;
                          const next = [...photos];
                          for (const asset of picked.assets) {
                            if (!asset.uri || next.length >= maxPhotos) break;
                            next.push({
                              uri: asset.uri,
                              fileName:
                                asset.fileName ||
                                `photo-${next.length + 1}.jpg`,
                            });
                          }
                          setPhotos(next);
                        })();
                      }}
                    >
                      <Text style={styles.photoAddText}>+</Text>
                    </Pressable>
                  ) : null}
                </View>
              </>
            ) : null}

            <PrimaryButton
              label={
                submitting ? t("sellToUs.submitting") : t("sellToUs.submit")
              }
              disabled={submitting}
              onPress={() => {
                if (!type || !name.trim() || !email.trim() || !phone.trim()) {
                  toast.error(t("sellToUs.submitFailed"));
                  return;
                }
                if (!city) {
                  toast.error(t("sellToUs.selectCity"));
                  return;
                }
                if (
                  purchasedFromUs &&
                  requireVerify &&
                  (!invoiceVerified || !verifiedTxnId)
                ) {
                  toast.error(t("sellToUs.verifyRequired"));
                  return;
                }
                if (type === "disc" && !gameTitle.trim()) {
                  toast.error(t("sellToUs.gameTitle"));
                  return;
                }
                if (type === "device" && (!model.trim() || !condition)) {
                  toast.error(t("sellToUs.submitFailed"));
                  return;
                }
                setSubmitting(true);
                void (async () => {
                  try {
                    const body = new FormData();
                    body.append("type", type);
                    body.append("name", name.trim());
                    body.append("email", email.trim());
                    body.append("phone", phone.trim());
                    body.append("city", city.trim());
                    body.append("notes", notes.trim());
                    body.append(
                      "purchased_from_us",
                      purchasedFromUs ? "1" : "0",
                    );
                    if (purchasedFromUs) {
                      body.append("invoice_no", invoiceNo.trim());
                      if (verifiedTxnId) {
                        body.append("transaction_id", String(verifiedTxnId));
                      }
                    }
                    const details: Record<string, string | null> = {};
                    if (type === "account") {
                      details.account_note = accountNote.trim();
                    } else if (type === "disc") {
                      details.game_title = gameTitle.trim();
                      details.platform = platform || null;
                      details.condition = condition || null;
                    } else if (type === "device") {
                      details.model = model.trim();
                      details.storage = storage.trim();
                      details.condition = condition || null;
                    }
                    body.append("details", JSON.stringify(details));
                    for (let i = 0; i < photos.length; i += 1) {
                      await appendSellToUsPhoto(body, photos[i].uri, i);
                    }
                    await submitSellToUsRequest(token, body);
                    toast.success(t("sellToUs.success"));
                    setType("");
                    setNotes("");
                    setInvoiceNo("");
                    setPurchasedFromUs(false);
                    setInvoiceVerified(false);
                    setVerifiedTxnId(null);
                    setAccountNote("");
                    setGameTitle("");
                    setPlatform("");
                    setCondition("");
                    setModel("");
                    setStorage("");
                    setPhotos([]);
                  } catch (e) {
                    toast.error(
                      e instanceof ApiError
                        ? e.message
                        : t("sellToUs.submitFailed"),
                    );
                  } finally {
                    setSubmitting(false);
                  }
                })();
              }}
            />
          </>
        )}
      </FormScrollView>
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
  muted: { color: "#777", marginBottom: 12, lineHeight: 20 },
  section: { fontWeight: "800", marginTop: 8, marginBottom: 6 },
  switchRow: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginVertical: 10,
    gap: 12,
  },
  switchLabel: { flex: 1, fontWeight: "600", color: "#333" },
  verifyRow: { gap: 8, marginBottom: 8 },
  ok: { fontWeight: "700", marginBottom: 8 },
  photoRow: { flexDirection: "row", flexWrap: "wrap", gap: 8, marginBottom: 16 },
  photo: { width: 72, height: 72, borderRadius: 8 },
  photoAdd: {
    width: 72,
    height: 72,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: "#ccc",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
  },
  photoAddText: { fontSize: 28, color: "#666" },
});
