import { Image, type ImageContentFit } from "expo-image";
import { StyleSheet, View, type StyleProp, type ViewStyle } from "react-native";

type Props = {
  uri?: string | null;
  style?: StyleProp<ViewStyle>;
  contentFit?: ImageContentFit;
  /** Optional placeholder color behind remote image. */
  placeholderColor?: string;
  recyclingKey?: string;
};

/**
 * Cached remote image via expo-image (memory + disk).
 * Falls back to a neutral placeholder when uri is missing.
 */
export function RemoteImage({
  uri,
  style,
  contentFit = "cover",
  placeholderColor = "#eee",
  recyclingKey,
}: Props) {
  if (!uri) {
    return (
      <View style={[styles.ph, { backgroundColor: placeholderColor }, style]} />
    );
  }

  return (
    <Image
      source={{ uri }}
      style={style as never}
      contentFit={contentFit}
      cachePolicy="memory-disk"
      transition={180}
      recyclingKey={recyclingKey || uri}
    />
  );
}

const styles = StyleSheet.create({
  ph: { backgroundColor: "#eee" },
});
