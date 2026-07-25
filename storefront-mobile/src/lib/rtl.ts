import { I18nManager } from "react-native";
import { useApp } from "../contexts/AppContext";

/** Layout helpers for Arabic RTL vs LTR. */
export function useRtl() {
  const { locale } = useApp();
  const isRtl = locale === "ar" || I18nManager.isRTL;
  return {
    isRtl,
    row: (isRtl ? "row-reverse" : "row") as "row" | "row-reverse",
    textAlign: (isRtl ? "right" : "left") as "left" | "right",
    writingDirection: (isRtl ? "rtl" : "ltr") as "rtl" | "ltr",
    start: (isRtl ? "right" : "left") as "left" | "right",
    end: (isRtl ? "left" : "right") as "left" | "right",
  };
}
