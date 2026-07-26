import { useEffect, useState, type ReactNode } from "react";
import { Image, Keyboard, StyleSheet, Text, View } from "react-native";
import { useApp } from "../../contexts/AppContext";
import { useRtl } from "../../lib/rtl";
import { FormScrollView } from "../FormScrollView";
import { Screen } from "../ui";

/** Light branded shell for login / register / verify screens. */
export function AuthScreenShell({
  title,
  children,
  footer,
}: {
  title: string;
  children: ReactNode;
  footer?: ReactNode;
}) {
  const { accent } = useApp();
  const { textAlign, writingDirection } = useRtl();
  const [keyboardOpen, setKeyboardOpen] = useState(false);

  useEffect(() => {
    const show = Keyboard.addListener("keyboardDidShow", () =>
      setKeyboardOpen(true),
    );
    const hide = Keyboard.addListener("keyboardDidHide", () =>
      setKeyboardOpen(false),
    );
    return () => {
      show.remove();
      hide.remove();
    };
  }, []);

  return (
    <Screen padded={false} avoidKeyboard={false}>
      <FormScrollView
        contentContainerStyle={[
          styles.pad,
          keyboardOpen && styles.padCompact,
        ]}
        bottomInset={keyboardOpen ? 120 : 72}
        bottomOffset={keyboardOpen ? 100 : 72}
      >
        {/* Brand mark — shrinks when keyboard is open so fields stay visible */}
        {!keyboardOpen ? (
          <View style={styles.logoWrap}>
            <View style={[styles.logoRing, { borderColor: accent }]}>
              <Image
                source={require("../../../assets/images/splash-logo.png")}
                style={styles.logo}
                resizeMode="contain"
              />
            </View>
          </View>
        ) : null}
        <Text
          style={[
            styles.title,
            keyboardOpen && styles.titleCompact,
            { textAlign, writingDirection, color: "#111" },
          ]}
        >
          {title}
        </Text>
        <View style={styles.form}>{children}</View>
        {footer ? <View style={styles.footer}>{footer}</View> : null}
      </FormScrollView>
    </Screen>
  );
}

export const authFieldStyles = StyleSheet.create({
  input: {
    backgroundColor: "#fff",
    borderRadius: 12,
    borderWidth: 1,
    borderColor: "#e5e5e5",
    paddingHorizontal: 14,
    paddingVertical: 14,
    marginBottom: 12,
    fontSize: 16,
    color: "#111",
  },
  error: { color: "#B00020", marginBottom: 10 },
  hint: { color: "#888", fontSize: 13, marginBottom: 14, lineHeight: 18 },
  linkRow: {
    marginTop: 8,
    marginBottom: 4,
  },
  link: { color: "#666", fontSize: 14 },
  linkAccent: { fontWeight: "700" },
});

const styles = StyleSheet.create({
  pad: {
    paddingHorizontal: 24,
    paddingTop: 24,
    paddingBottom: 40,
    flexGrow: 1,
  },
  padCompact: {
    paddingTop: 8,
  },
  logoWrap: { alignItems: "center", marginBottom: 20 },
  logoRing: {
    width: 96,
    height: 96,
    borderRadius: 48,
    borderWidth: 3,
    alignItems: "center",
    justifyContent: "center",
    backgroundColor: "#fff",
    overflow: "hidden",
  },
  logo: { width: 72, height: 72 },
  title: {
    fontSize: 26,
    fontWeight: "800",
    textAlign: "center",
    marginBottom: 24,
  },
  titleCompact: {
    fontSize: 20,
    marginBottom: 12,
  },
  form: { width: "100%" },
  footer: { marginTop: 24, alignItems: "center" },
});
