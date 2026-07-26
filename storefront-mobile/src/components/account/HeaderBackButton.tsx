import { Pressable, StyleSheet } from "react-native";
import { Ionicons } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { useRtl } from "../../lib/rtl";

/** Explicit back control for account stack screens. */
export function HeaderBackButton({ fallbackHref = "/(tabs)/account" }: { fallbackHref?: string }) {
  const router = useRouter();
  const { isRtl } = useRtl();

  return (
    <Pressable
      onPress={() => {
        if (router.canGoBack()) {
          router.back();
        } else {
          router.replace(fallbackHref as never);
        }
      }}
      hitSlop={10}
      style={styles.btn}
    >
      <Ionicons
        name={isRtl ? "chevron-forward" : "chevron-back"}
        size={26}
        color="#111"
      />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  btn: { paddingHorizontal: 4, paddingVertical: 4 },
});
