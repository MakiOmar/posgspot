import * as LocalAuthentication from "expo-local-authentication";
import * as SecureStore from "expo-secure-store";

const FLAG_KEY = "gs-biometric-unlock-v1";

const SECURE_OPTS: SecureStore.SecureStoreOptions = {
  keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
};

export async function isBiometricUnlockEnabled(): Promise<boolean> {
  try {
    return (await SecureStore.getItemAsync(FLAG_KEY, SECURE_OPTS)) === "1";
  } catch {
    return false;
  }
}

export async function setBiometricUnlockEnabled(enabled: boolean): Promise<void> {
  if (enabled) {
    await SecureStore.setItemAsync(FLAG_KEY, "1", SECURE_OPTS);
    return;
  }
  try {
    await SecureStore.deleteItemAsync(FLAG_KEY, SECURE_OPTS);
  } catch {
    // ignore
  }
}

export async function deviceHasBiometrics(): Promise<boolean> {
  try {
    const hasHardware = await LocalAuthentication.hasHardwareAsync();
    const enrolled = await LocalAuthentication.isEnrolledAsync();
    return hasHardware && enrolled;
  } catch {
    return false;
  }
}

export async function authenticateBiometric(prompt: string): Promise<boolean> {
  try {
    const result = await LocalAuthentication.authenticateAsync({
      promptMessage: prompt,
      cancelLabel: "Cancel",
      disableDeviceFallback: false,
    });
    return result.success;
  } catch {
    return false;
  }
}
