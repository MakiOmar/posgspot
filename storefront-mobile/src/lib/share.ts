import { Platform, Share } from "react-native";

/** Same share-intent URL formats as the website product share buttons. */
export function shareTargetUrls(title: string, url: string) {
  return {
    whatsapp: `https://wa.me/?text=${encodeURIComponent(`${title} ${url}`)}`,
    facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
    x: `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`,
  };
}

/** Native share sheet; resolves false only on a real failure (dismissing is not an error). */
export async function shareNative(title: string, url: string, dialogTitle: string): Promise<boolean> {
  try {
    await Share.share(
      Platform.OS === "ios" ? { title, message: title, url } : { title, message: `${title}\n${url}` },
      { dialogTitle },
    );
    return true;
  } catch (e) {
    const message = e && typeof e === "object" && "message" in e ? String(e.message || "") : "";
    return /cancel|dismiss/i.test(message);
  }
}
