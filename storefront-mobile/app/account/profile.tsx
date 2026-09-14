import { useCallback, useEffect, useState } from "react";
import {
  Alert,
  Pressable,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { Redirect, useRouter, type Href } from "expo-router";
import * as ImagePicker from "expo-image-picker";
import {
  deleteProfileAvatar,
  fetchPhoneCountries,
  fetchProfile,
  requestAccountDeletion,
  updateProfile,
  uploadProfileAvatar,
} from "../../src/lib/api";
import { useApp } from "../../src/contexts/AppContext";
import { LabeledInput } from "../../src/components/LabeledInput";
import { PhoneInput } from "../../src/components/PhoneInput";
import { RemoteImage } from "../../src/components/RemoteImage";
import {
  ErrorBlock,
  FormScrollView,
  LoadingBlock,
  PrimaryButton,
  Screen,
} from "../../src/components/ui";
import { parseStoredMobile } from "../../src/lib/phone-parse";
import { useRtl } from "../../src/lib/rtl";
import { toast } from "../../src/lib/toast";
import type { AuthContact } from "../../src/lib/types";

function splitMobile(mobile: string | undefined): { dial: string; national: string } {
  const parsed = parseStoredMobile(mobile);
  return { dial: parsed.dialCode, national: parsed.nationalNumber };
}

export default function ProfileScreen() {
  const { token, t, accent, contact, updateContactLocal } = useApp();
  const router = useRouter();
  const { textAlign, writingDirection, row } = useRtl();
  const cachedPhone = splitMobile(contact?.mobile);
  const [firstName, setFirstName] = useState(contact?.first_name || "");
  const [lastName, setLastName] = useState(contact?.last_name || "");
  const [email, setEmail] = useState(contact?.email || "");
  const [dialCode, setDialCode] = useState(cachedPhone.dial);
  const [national, setNational] = useState(cachedPhone.national);
  const [fullPhone, setFullPhone] = useState(contact?.mobile || "");
  const [emailVerified, setEmailVerified] = useState(contact?.email_verified !== false);
  const [deleteRequested, setDeleteRequested] = useState(!!contact?.delete_requested);
  const [avatarUrl, setAvatarUrl] = useState<string | null>(contact?.avatar_url || null);
  const [loading, setLoading] = useState(!contact);
  const [busy, setBusy] = useState(false);
  const [avatarBusy, setAvatarBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const applyContact = useCallback(
    (data: AuthContact, countries: { dial_code: string }[] = []) => {
      setFirstName(data.first_name || "");
      setLastName(data.last_name || "");
      setEmail(data.email || "");
      const split = parseStoredMobile(data.mobile, countries);
      setDialCode(split.dialCode);
      setNational(split.nationalNumber);
      setFullPhone(
        data.mobile?.startsWith("+")
          ? data.mobile
          : split.nationalNumber
            ? `${split.dialCode}${split.nationalNumber}`
            : "",
      );
      setEmailVerified(!!data.email_verified);
      setDeleteRequested(!!data.delete_requested);
      setAvatarUrl(data.avatar_url || null);
    },
    [],
  );

  const load = useCallback(async () => {
    if (!token) {
      setLoading(false);
      return;
    }
    try {
      const [{ data }, geo] = await Promise.all([
        fetchProfile(token),
        fetchPhoneCountries().catch(() => ({ data: [] as { dial_code: string }[] })),
      ]);
      applyContact(data, geo.data || []);
      void updateContactLocal(data);
      setError(null);
    } catch (e) {
      setError(e instanceof Error ? e.message : t("common.error"));
    } finally {
      setLoading(false);
    }
  }, [token, applyContact, updateContactLocal, t]);

  // Fetch once per token. Do not depend on session/contact — that remounted the form.
  useEffect(() => {
    void load();
  }, [token, load]);

  if (!token) {
    return <Redirect href="/login" />;
  }

  if (loading) {
    return (
      <Screen>
        <LoadingBlock />
      </Screen>
    );
  }

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView contentContainerStyle={styles.pad}>
        <Text style={[styles.lead, { textAlign, writingDirection }]}>
          {t("account.editPersonal")}
        </Text>
        {error ? (
          <ErrorBlock message={error} onRetry={() => void load()} />
        ) : null}

        <View style={styles.avatarBlock}>
          <View style={[styles.avatarCircle, { borderColor: accent }]}>
            {avatarUrl ? (
              <RemoteImage
                uri={avatarUrl}
                style={styles.avatarImage}
                contentFit="cover"
              />
            ) : (
              <Text style={styles.avatarPlaceholder}>
                {(firstName || email || "?").slice(0, 1).toUpperCase()}
              </Text>
            )}
          </View>
          <Pressable
            disabled={avatarBusy}
            onPress={() => {
              void (async () => {
                const permission =
                  await ImagePicker.requestMediaLibraryPermissionsAsync();
                if (!permission.granted) {
                  toast.error(t("account.avatarPermission"));
                  return;
                }
                const picked = await ImagePicker.launchImageLibraryAsync({
                  mediaTypes: ["images"],
                  allowsEditing: true,
                  aspect: [1, 1],
                  quality: 0.85,
                });
                if (picked.canceled || !picked.assets[0]?.uri) {
                  return;
                }
                const asset = picked.assets[0];
                const name =
                  asset.fileName ||
                  (asset.uri.toLowerCase().includes(".png")
                    ? "avatar.png"
                    : "avatar.jpg");
                setAvatarBusy(true);
                try {
                  const { data } = await uploadProfileAvatar(
                    token,
                    asset.uri,
                    name,
                  );
                  setAvatarUrl(data.avatar_url || null);
                  await updateContactLocal(data);
                  toast.success(t("account.avatarSaved"));
                } catch {
                  toast.error(t("account.avatarFailed"));
                } finally {
                  setAvatarBusy(false);
                }
              })();
            }}
          >
            <Text style={{ color: accent, fontWeight: "700" }}>
              {avatarBusy ? t("common.loading") : t("account.changeAvatar")}
            </Text>
          </Pressable>
          {avatarUrl ? (
            <Pressable
              disabled={avatarBusy}
              onPress={() => {
                void (async () => {
                  setAvatarBusy(true);
                  try {
                    const { data } = await deleteProfileAvatar(token);
                    setAvatarUrl(null);
                    await updateContactLocal(data);
                    toast.success(t("account.avatarRemoved"));
                  } catch (e) {
                    toast.error(
                      e instanceof Error
                        ? e.message
                        : t("account.avatarFailed"),
                    );
                  } finally {
                    setAvatarBusy(false);
                  }
                })();
              }}
            >
              <Text style={styles.removeAvatar}>{t("account.removeAvatar")}</Text>
            </Pressable>
          ) : null}
        </View>

        <LabeledInput
          label={t("auth.firstName")}
          value={firstName}
          onChangeText={setFirstName}
        />
        <LabeledInput
          label={t("auth.lastName")}
          value={lastName}
          onChangeText={setLastName}
        />
        <PhoneInput
          label={t("auth.mobileOptional")}
          dialCode={dialCode}
          nationalNumber={national}
          onChange={({ dialCode: d, nationalNumber: n, fullPhone: f }) => {
            setDialCode(d);
            setNational(n);
            setFullPhone(f);
          }}
        />

        <View style={[styles.inlineField, { flexDirection: row }]}>
          <View style={styles.flex}>
            <LabeledInput
              label={t("auth.email")}
              value={email}
              onChangeText={setEmail}
              autoCapitalize="none"
              keyboardType="email-address"
            />
          </View>
          {!emailVerified ? (
            <Pressable
              onPress={() =>
                router.push({
                  pathname: "/verify-email",
                  params: { email },
                } as unknown as Href)
              }
              style={styles.inlineAction}
            >
              <Text style={{ color: accent, fontWeight: "700" }}>
                {t("account.verifyEmail")}
              </Text>
            </Pressable>
          ) : null}
        </View>

        <PrimaryButton
          label={busy ? t("common.loading") : t("account.saveProfile")}
          disabled={busy}
          onPress={() => {
            setBusy(true);
            void updateProfile(token, {
              first_name: firstName.trim(),
              last_name: lastName.trim(),
              email: email.trim(),
              mobile: fullPhone || (national ? `${dialCode}${national}` : undefined),
              dial_code: dialCode,
            })
              .then(async ({ data }) => {
                await updateContactLocal(data);
                setEmailVerified(!!data.email_verified);
                toast.success(t("account.profileSaved"));
                if (data.email_verified === false) {
                  router.push({
                    pathname: "/verify-email",
                    params: { email: data.email || email },
                  } as unknown as Href);
                }
              })
              .catch((e) =>
                toast.error(
                  e instanceof Error ? e.message : t("common.error"),
                ),
              )
              .finally(() => setBusy(false));
          }}
        />

        <Pressable
          style={styles.deleteLink}
          onPress={() => {
            if (deleteRequested) {
              toast.info(t("account.deleteRequested"));
              return;
            }
            Alert.alert(t("account.deleteRequest"), t("account.deleteConfirm"), [
              { text: t("common.cancel"), style: "cancel" },
              {
                text: t("account.deleteRequest"),
                style: "destructive",
                onPress: () => {
                  void requestAccountDeletion(token)
                    .then(async ({ data }) => {
                      if (data.contact) {
                        await updateContactLocal(data.contact);
                      }
                      setDeleteRequested(true);
                      toast.success(t("account.deleteRequested"));
                    })
                    .catch((e) =>
                      toast.error(
                        e instanceof Error ? e.message : t("common.error"),
                      ),
                    );
                },
              },
            ]);
          }}
        >
          <Text style={[styles.deleteText, { textAlign, writingDirection }]}>
            {deleteRequested
              ? t("account.deleteRequested")
              : t("account.deleteRequest")}
          </Text>
        </Pressable>
      </FormScrollView>
    </Screen>
  );
}

const styles = StyleSheet.create({
  pad: { padding: 16, paddingBottom: 40 },
  lead: { color: "#888", marginBottom: 16 },
  avatarBlock: { alignItems: "center", gap: 8, marginBottom: 20 },
  avatarCircle: {
    width: 96,
    height: 96,
    borderRadius: 48,
    borderWidth: 2,
    overflow: "hidden",
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
  },
  avatarImage: { width: 96, height: 96 },
  avatarPlaceholder: { fontSize: 36, fontWeight: "800", color: "#999" },
  removeAvatar: { color: "#888", marginTop: 4 },
  inlineField: { alignItems: "flex-end", gap: 8 },
  flex: { flex: 1 },
  inlineAction: { paddingBottom: 24, paddingHorizontal: 4 },
  deleteLink: { marginTop: 28, alignItems: "center" },
  deleteText: { color: "#111", fontSize: 15 },
});
