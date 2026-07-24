import { Redirect } from "expo-router";

/** Deep-link alias → wishlist tab. */
export default function WishlistRedirect() {
  return <Redirect href="/(tabs)/wishlist" />;
}
