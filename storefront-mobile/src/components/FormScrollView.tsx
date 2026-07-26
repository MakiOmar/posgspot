import { forwardRef } from "react";
import { StyleSheet, type StyleProp, type ViewStyle } from "react-native";
import {
  KeyboardAwareScrollView,
  type KeyboardAwareScrollViewProps,
  type KeyboardAwareScrollViewRef,
} from "react-native-keyboard-controller";

type Props = KeyboardAwareScrollViewProps & {
  /** Extra space below the focused field (above keyboard / CTA). */
  bottomInset?: number;
  containerStyle?: StyleProp<ViewStyle>;
};

/**
 * Form scroll that keeps the focused input above the soft keyboard (iOS + Android).
 * Prefer `Screen avoidKeyboard={false}` when wrapping with this.
 */
export const FormScrollView = forwardRef<KeyboardAwareScrollViewRef, Props>(
  function FormScrollView(
    {
      children,
      contentContainerStyle,
      containerStyle,
      bottomInset = 48,
      bottomOffset,
      keyboardShouldPersistTaps = "handled",
      keyboardDismissMode = "interactive",
      showsVerticalScrollIndicator = false,
      style,
      ...rest
    },
    ref,
  ) {
    return (
      <KeyboardAwareScrollView
        ref={ref}
        style={[styles.flex, containerStyle, style]}
        contentContainerStyle={[
          styles.content,
          { paddingBottom: bottomInset },
          contentContainerStyle,
        ]}
        bottomOffset={bottomOffset ?? bottomInset}
        keyboardShouldPersistTaps={keyboardShouldPersistTaps}
        keyboardDismissMode={keyboardDismissMode}
        showsVerticalScrollIndicator={showsVerticalScrollIndicator}
        extraKeyboardSpace={16}
        {...rest}
      >
        {children}
      </KeyboardAwareScrollView>
    );
  },
);

const styles = StyleSheet.create({
  flex: { flex: 1 },
  content: { flexGrow: 1 },
});
