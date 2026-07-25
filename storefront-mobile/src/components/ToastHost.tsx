import { useEffect, useRef, useState } from "react";
import { Pressable, StyleSheet, Text, View } from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { toast, type ToastPayload } from "../lib/toast";
import { useRtl } from "../lib/rtl";

const KIND_COLORS = {
  success: { bg: "#0B6E4F", border: "#086048" },
  error: { bg: "#B00020", border: "#8A0019" },
  info: { bg: "#1F2937", border: "#111827" },
} as const;

/**
 * Root toast host — mount once under SafeAreaProvider + AppProvider.
 */
export function ToastHost() {
  const insets = useSafeAreaInsets();
  const { textAlign, writingDirection } = useRtl();
  const [items, setItems] = useState<ToastPayload[]>([]);
  const timers = useRef<Map<string, ReturnType<typeof setTimeout>>>(new Map());

  useEffect(() => {
    return toast.subscribe((next) => {
      setItems((prev) => [...prev.slice(-2), next]);
      const existing = timers.current.get(next.id);
      if (existing) clearTimeout(existing);
      const timer = setTimeout(() => {
        setItems((prev) => prev.filter((t) => t.id !== next.id));
        timers.current.delete(next.id);
      }, next.durationMs);
      timers.current.set(next.id, timer);
    });
  }, []);

  useEffect(() => {
    return () => {
      timers.current.forEach(clearTimeout);
      timers.current.clear();
    };
  }, []);

  if (!items.length) return null;

  return (
    <View
      pointerEvents="box-none"
      style={[
        styles.host,
        { top: Math.max(insets.top, 8) + 8, paddingHorizontal: 16 },
      ]}
    >
      {items.map((item) => {
        const colors = KIND_COLORS[item.kind];
        return (
          <Pressable
            key={item.id}
            onPress={() => {
              const timer = timers.current.get(item.id);
              if (timer) clearTimeout(timer);
              timers.current.delete(item.id);
              setItems((prev) => prev.filter((t) => t.id !== item.id));
            }}
            style={[
              styles.toast,
              { backgroundColor: colors.bg, borderColor: colors.border },
            ]}
          >
            <Text
              style={[styles.title, { textAlign, writingDirection }]}
              numberOfLines={2}
            >
              {item.title}
            </Text>
            {item.message ? (
              <Text
                style={[styles.message, { textAlign, writingDirection }]}
                numberOfLines={3}
              >
                {item.message}
              </Text>
            ) : null}
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  host: {
    position: "absolute",
    left: 0,
    right: 0,
    zIndex: 9999,
    elevation: 9999,
    gap: 8,
  },
  toast: {
    borderRadius: 12,
    borderWidth: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
    shadowColor: "#000",
    shadowOpacity: 0.2,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
  },
  title: { color: "#fff", fontWeight: "800", fontSize: 15 },
  message: { color: "rgba(255,255,255,0.92)", marginTop: 4, fontSize: 13 },
});
