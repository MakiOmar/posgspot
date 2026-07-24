import { useEffect } from "react";
import { Image, StyleSheet, View } from "react-native";
import Animated, {
  Easing,
  useAnimatedStyle,
  useSharedValue,
  withRepeat,
  withTiming,
} from "react-native-reanimated";
import Svg, { Circle } from "react-native-svg";

const ORANGE = "#FF7A00";
const SIZE = 300;
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
    <Animated.View style={[styles.ring, spinStyle]}>
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

/**
 * Full-logo branded splash with two opposite-rotating 3/4 arcs.
 */
export function BrandSplash() {
  return (
    <View style={styles.root} pointerEvents="auto">
      <View style={styles.stage}>
        <ArcRing radius={128} durationMs={2400} strokeWidth={3.5} />
        <ArcRing radius={110} durationMs={1700} reverse strokeWidth={2.75} />
        <Image
          source={require("../../assets/images/splash-logo.png")}
          style={styles.logo}
          resizeMode="contain"
        />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: "#000000",
    alignItems: "center",
    justifyContent: "center",
    zIndex: 1000,
  },
  stage: {
    width: SIZE,
    height: SIZE,
    alignItems: "center",
    justifyContent: "center",
  },
  ring: {
    ...StyleSheet.absoluteFillObject,
  },
  logo: {
    width: SIZE * 0.58,
    height: SIZE * 0.58,
  },
});
