import { Platform } from "react-native";

/**
 * Offset for KeyboardAvoidingView when it already sits below the nav header.
 * Keep modest — oversized offsets push forms too high on tab screens.
 */
export function useKeyboardVerticalOffset(extra = 0): number {
  if (Platform.OS !== "ios") {
    return extra;
  }
  return extra;
}
