import FontAwesome from "@expo/vector-icons/FontAwesome";
import {
  Dimensions,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from "react-native";
import { RemoteImage } from "../RemoteImage";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";

const SCREEN_W = Dimensions.get("window").width;

type Props = {
  images: string[];
  galleryIndex: number;
  onGalleryIndexChange: (index: number) => void;
  wished: boolean;
  onShare: () => void;
  onToggleWishlist: () => void;
};

/** Horizontal image pager with floating share / wishlist actions. */
export function ProductGallery({
  images,
  galleryIndex,
  onGalleryIndexChange,
  wished,
  onShare,
  onToggleWishlist,
}: Props) {
  const { t, accent } = useApp();
  const { end } = useRtl();

  return (
    <View style={styles.galleryWrap}>
      <ScrollView
        horizontal
        pagingEnabled
        showsHorizontalScrollIndicator={false}
        onMomentumScrollEnd={(e) => {
          const i = Math.round(e.nativeEvent.contentOffset.x / SCREEN_W);
          onGalleryIndexChange(i);
        }}
      >
        {(images.length ? images : [null]).map((uri, idx) => (
          <RemoteImage
            key={`${uri || "ph"}-${idx}`}
            uri={uri}
            style={styles.image}
            placeholderColor="#eee"
          />
        ))}
      </ScrollView>

      {/* Floating share + wishlist — top-end (right LTR / left RTL) */}
      <View
        style={[styles.galleryActions, { [end]: 12 }]}
        pointerEvents="box-none"
      >
        <Pressable
          style={[styles.shareBtn, { backgroundColor: accent }]}
          onPress={onShare}
          accessibilityRole="button"
          accessibilityLabel={t("share.label")}
        >
          <FontAwesome name="share-alt" size={18} color="#111" />
        </Pressable>
        <Pressable
          style={styles.wishBtn}
          onPress={onToggleWishlist}
          accessibilityRole="button"
          accessibilityLabel={t("nav.wishlist")}
        >
          <FontAwesome
            name={wished ? "heart" : "heart-o"}
            size={18}
            color={wished ? "#FF6B8A" : "#fff"}
          />
        </Pressable>
      </View>

      {images.length > 1 ? (
        <View style={styles.dotsWrap} pointerEvents="none">
          <Text style={styles.dots}>
            {galleryIndex + 1}/{images.length}
          </Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  galleryWrap: {
    position: "relative",
    width: SCREEN_W,
    height: 300,
    backgroundColor: "#eee",
  },
  image: { width: SCREEN_W, height: 300, backgroundColor: "#eee" },
  galleryActions: {
    position: "absolute",
    top: 12,
    gap: 10,
    alignItems: "center",
    zIndex: 2,
  },
  shareBtn: {
    width: 46,
    height: 46,
    borderRadius: 23,
    alignItems: "center",
    justifyContent: "center",
    shadowColor: "#000",
    shadowOpacity: 0.22,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 4 },
    elevation: 6,
  },
  wishBtn: {
    width: 46,
    height: 46,
    borderRadius: 23,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "rgba(20, 20, 20, 0.55)",
    borderWidth: StyleSheet.hairlineWidth,
    borderColor: "rgba(255,255,255,0.25)",
  },
  dotsWrap: {
    position: "absolute",
    bottom: 10,
    left: 0,
    right: 0,
    alignItems: "center",
  },
  dots: {
    color: "#fff",
    backgroundColor: "rgba(0,0,0,0.45)",
    overflow: "hidden",
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    fontSize: 12,
    fontWeight: "700",
  },
});
