import { forwardRef } from "react";
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  type ScrollViewProps,
  type StyleProp,
  type ViewStyle,
} from "react-native";
import { useKeyboardVerticalOffset } from "../lib/keyboard";

type Props = ScrollViewProps & {
  /** Extra bottom padding so the last field clears the keyboard/CTA. */
  bottomInset?: number;
  containerStyle?: StyleProp<ViewStyle>;
};

/**
 * Keyboard-safe scroll container for forms.
 * Use inside `Screen` with `avoidKeyboard={false}` to avoid nested avoiders.
 */
export const FormScrollView = forwardRef<ScrollView, Props>(function FormScrollView(
  {
    children,
    contentContainerStyle,
    containerStyle,
    bottomInset = 48,
    keyboardShouldPersistTaps = "handled",
    keyboardDismissMode = "on-drag",
    showsVerticalScrollIndicator = false,
    ...rest
  },
  ref,
) {
  const keyboardOffset = useKeyboardVerticalOffset();

  return (
    <KeyboardAvoidingView
      style={[styles.flex, containerStyle]}
      behavior={Platform.OS === "ios" ? "padding" : undefined}
      keyboardVerticalOffset={keyboardOffset}
    >
      <ScrollView
        ref={ref}
        style={styles.flex}
        contentContainerStyle={[
          styles.content,
          { paddingBottom: bottomInset },
          contentContainerStyle,
        ]}
        keyboardShouldPersistTaps={keyboardShouldPersistTaps}
        keyboardDismissMode={keyboardDismissMode}
        automaticallyAdjustKeyboardInsets
        showsVerticalScrollIndicator={showsVerticalScrollIndicator}
        {...rest}
      >
        {children}
      </ScrollView>
    </KeyboardAvoidingView>
  );
});

const styles = StyleSheet.create({
  flex: { flex: 1 },
  content: { flexGrow: 1 },
});
