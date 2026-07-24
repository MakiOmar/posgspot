import { Platform } from "react-native";
import Constants from "expo-constants";
import { registerDevice, unregisterDevice } from "./api";
import type { ContentLocale } from "./types";

async function notifications() {
  return import("expo-notifications");
}

export async function ensurePushPermission(): Promise<boolean> {
  const Notifications = await notifications();
  const current = await Notifications.getPermissionsAsync();
  if (current.granted) {
    return true;
  }
  const requested = await Notifications.requestPermissionsAsync();
  return requested.granted;
}

export async function getExpoPushToken(): Promise<string | null> {
  const granted = await ensurePushPermission();
  if (!granted) {
    return null;
  }

  const Notifications = await notifications();
  Notifications.setNotificationHandler({
    handleNotification: async () => ({
      shouldShowAlert: true,
      shouldPlaySound: false,
      shouldSetBadge: false,
      shouldShowBanner: true,
      shouldShowList: true,
    }),
  });

  try {
    const device = await Notifications.getDevicePushTokenAsync();
    if (device?.data) {
      return typeof device.data === "string"
        ? device.data
        : JSON.stringify(device.data);
    }
  } catch {
    // Fall through to Expo push token
  }

  const projectId =
    Constants.easConfig?.projectId ??
    (Constants.expoConfig?.extra as { eas?: { projectId?: string } } | undefined)
      ?.eas?.projectId;
  if (!projectId || projectId === "replace-with-eas-project-id") {
    return null;
  }
  try {
    const token = await Notifications.getExpoPushTokenAsync({ projectId });
    return token.data;
  } catch {
    return null;
  }
}

export async function syncPushTokenWithApi(
  authToken: string,
  locale: ContentLocale,
): Promise<string | null> {
  const pushToken = await getExpoPushToken();
  if (!pushToken) {
    return null;
  }
  const platform = Platform.OS === "ios" ? "ios" : "android";
  await registerDevice(authToken, {
    platform,
    token: pushToken,
    locale,
  });
  return pushToken;
}

export async function clearPushTokenFromApi(
  authToken: string,
  pushToken: string | null,
): Promise<void> {
  if (!pushToken) {
    return;
  }
  try {
    await unregisterDevice(authToken, pushToken);
  } catch {
    // ignore
  }
}
