import FontAwesome from "@expo/vector-icons/FontAwesome";
import { useEffect, useRef, useState } from "react";
import {
  FlatList,
  Modal,
  Pressable,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from "react-native";
import { useSafeAreaInsets } from "react-native-safe-area-context";
import { useApp } from "../../contexts/AppContext";
import { RemoteImage } from "../RemoteImage";

type Props = {
  images: string[];
  /** Index to open at; `null` keeps the viewer closed. */
  startIndex: number | null;
  onClose: () => void;
};

/** Fullscreen image viewer with swipe, prev/next, and close (PDP + community galleries). */
export function ImageLightbox({ images, startIndex, onClose }: Props) {
  const { t } = useApp();
  const insets = useSafeAreaInsets();
  const { width, height } = useWindowDimensions();
  const listRef = useRef<FlatList<string>>(null);
  const open = startIndex !== null && images.length > 0;
  const initialIndex = Math.min(Math.max(startIndex ?? 0, 0), Math.max(images.length - 1, 0));
  const [index, setIndex] = useState(initialIndex);

  useEffect(() => {
    setIndex(initialIndex);
  }, [initialIndex, open]);

  const goTo = (next: number) => {
    if (next < 0 || next >= images.length) return;
    setIndex(next);
    listRef.current?.scrollToIndex({ index: next, animated: true });
  };

  return (
    <Modal visible={open} animationType="fade" onRequestClose={onClose} statusBarTranslucent>
      <View style={styles.backdrop} accessibilityLabel={t("lightbox.label")}>
        {open ? (
          <FlatList
            ref={listRef}
            data={images}
            horizontal
            pagingEnabled
            showsHorizontalScrollIndicator={false}
            initialScrollIndex={initialIndex}
            getItemLayout={(_, i) => ({ length: width, offset: width * i, index: i })}
            keyExtractor={(uri, i) => `${uri}-${i}`}
            onMomentumScrollEnd={(e) =>
              setIndex(Math.round(e.nativeEvent.contentOffset.x / Math.max(width, 1)))
            }
            renderItem={({ item }) => (
              <View style={{ width, height, justifyContent: "center" }}>
                <RemoteImage
                  uri={item}
                  style={{ width, height: height * 0.8 }}
                  contentFit="contain"
                  placeholderColor="#000"
                />
              </View>
            )}
          />
        ) : null}

        <Pressable
          onPress={onClose}
          style={[styles.close, { top: insets.top + 12 }]}
          accessibilityRole="button"
          accessibilityLabel={t("common.close")}
          hitSlop={8}
        >
          <FontAwesome name="close" size={22} color="#fff" />
        </Pressable>

        {images.length > 1 ? (
          <>
            <Pressable
              onPress={() => goTo(index - 1)}
              disabled={index === 0}
              style={[styles.nav, styles.prev, index === 0 && styles.navDisabled]}
              accessibilityRole="button"
              accessibilityLabel={t("lightbox.prev")}
            >
              <FontAwesome name="chevron-left" size={20} color="#fff" />
            </Pressable>
            <Pressable
              onPress={() => goTo(index + 1)}
              disabled={index >= images.length - 1}
              style={[styles.nav, styles.next, index >= images.length - 1 && styles.navDisabled]}
              accessibilityRole="button"
              accessibilityLabel={t("lightbox.next")}
            >
              <FontAwesome name="chevron-right" size={20} color="#fff" />
            </Pressable>
            <Text style={[styles.counter, { bottom: insets.bottom + 20 }]}>
              {index + 1}/{images.length}
            </Text>
          </>
        ) : null}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: { flex: 1, backgroundColor: "#000" },
  close: {
    position: "absolute",
    right: 16,
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(255,255,255,0.15)",
  },
  nav: {
    position: "absolute",
    top: "50%",
    marginTop: -24,
    width: 48,
    height: 48,
    borderRadius: 24,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(255,255,255,0.15)",
  },
  prev: { left: 12 },
  next: { right: 12 },
  navDisabled: { opacity: 0.3 },
  counter: {
    position: "absolute",
    alignSelf: "center",
    color: "#fff",
    fontWeight: "700",
    backgroundColor: "rgba(0,0,0,0.5)",
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: "hidden",
  },
});
