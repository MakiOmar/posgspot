import { useEffect } from "react";
import { Image, Modal, StyleSheet, View } from "react-native";
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from "react-native-reanimated";
import Svg, { Circle } from "react-native-svg";

const ORANGE = "#FF7A00";
/** Outer stage size — both arcs and logo share this box. */
const SIZE = 260;
const CENTER = SIZE / 2;

type ArcRingProps = {
  radius: number;
  durationMs: number;
  reverse?: boolean;
  strokeWidth?: number;
};

function ArcRing({
  radius,
  durationMs,
  reverse = false,
  strokeWidth = 3,
}: ArcRingProps) {
  const progress = useSharedValue(0);
  const circumference = 2 * Math.PI * radius;
  const arc = circumference * 0.75;

  useEffect(() => {
    progress.value = 0;
    progress.value = withRepeat(
      withTiming(1, { duration: durationMs, easing: Easing.linear }),
      -1,
      false,
    );
  }, [durationMs, progress]);

  const spinStyle = useAnimatedStyle(() => ({
    transform: [
      { rotate: `${progress.value * 360 * (reverse ? -1 : 1)}deg` },
    ],
  }));

  return (
    <Animated.View style={[styles.ringLayer, spinStyle]} pointerEvents="none">
      <Svg width={SIZE} height={SIZE}>
        <Circle
          cx={CENTER}
          cy={CENTER}
          r={radius}
          stroke={ORANGE}
          strokeWidth={strokeWidth}
          fill="none"
          strokeLinecap="round"
          strokeDasharray={`${arc} ${circumference}`}
        />
      </Svg>
    </Animated.View>
  );
}

type Props = {
  visible: boolean;
};

/**
 * Full-screen branded splash: logo centered with two nested opposite-rotating ¾ arcs.
 * Uses Modal so rings cannot leak into the app after dismiss.
 */
export function BrandSplash({ visible }: Props) {
  return (
    <Modal
      visible={visible}
      animationType="none"
      transparent={false}
      statusBarTranslucent
      onRequestClose={() => undefined}
    >
      <View style={styles.root}>
        <View style={styles.stage}>
          {/* Outer ring */}
          <ArcRing radius={118} durationMs={2400} strokeWidth={3.5} />
          {/* Inner ring — opposite direction */}
          <ArcRing radius={98} durationMs={1700} reverse strokeWidth={2.75} />
          <Image
            source={require("../../assets/images/splash-logo.png")}
            style={styles.logo}
            resizeMode="contain"
          />
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: "#000000",
    alignItems: "center",
    justifyContent: "center",
  },
  stage: {
    width: SIZE,
    height: SIZE,
    alignItems: "center",
    justifyContent: "center",
    overflow: "hidden",
  },
  ringLayer: {
    position: "absolute",
    top: 0,
    left: 0,
    width: SIZE,
    height: SIZE,
  },
  logo: {
    width: SIZE * 0.55,
    height: SIZE * 0.55,
  },
});
