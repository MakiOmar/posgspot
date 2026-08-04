import { type ReactNode } from "react";
import { Linking, Pressable, View } from "react-native";
import { useRouter } from "expo-router";
import { hrefToAppPath } from "../../lib/storefront-href";

export function AppLink({
  href,
  children,
  style,
}: {
  href: string | null | undefined;
  children: ReactNode;
  style?: object;
}) {
  const router = useRouter();
  const appPath = hrefToAppPath(href);
  if (!appPath) {
    return <View style={style}>{children}</View>;
  }
  if (appPath.startsWith("http")) {
    return (
      <Pressable style={style} onPress={() => void Linking.openURL(appPath)}>
        {children}
      </Pressable>
    );
  }
  return (
    <Pressable style={style} onPress={() => router.push(appPath as never)}>
      {children}
    </Pressable>
  );
}
