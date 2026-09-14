import { Redirect } from "expo-router";

/** Legacy password route — Login & Security is the canonical screen. */
export default function ChangePasswordRedirect() {
  return <Redirect href="/account/security" />;
}
