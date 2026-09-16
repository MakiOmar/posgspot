/**
 * Mobile OAuth via AuthSession — public client IDs only (never secrets).
 */
import { Platform } from "react-native";
import * as AuthSession from "expo-auth-session";
import * as WebBrowser from "expo-web-browser";
import * as Crypto from "expo-crypto";

WebBrowser.maybeCompleteAuthSession();

export type SocialProvider = "google" | "facebook";

function googleClientId(): string {
  const android = (process.env.EXPO_PUBLIC_GOOGLE_ANDROID_CLIENT_ID || "").trim();
  const ios = (process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID || "").trim();
  const web = (process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID || "").trim();
  if (Platform.OS === "android" && android) return android;
  if (Platform.OS === "ios" && ios) return ios;
  return web || android || ios;
}

function facebookAppId(): string {
  return (process.env.EXPO_PUBLIC_FACEBOOK_APP_ID || "").trim();
}

async function randomNonce(): Promise<string> {
  const bytes = await Crypto.getRandomBytesAsync(16);
  return Array.from(bytes)
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}

/**
 * Google: PKCE + id_token (openid). Returns tokens for POST /auth/social/google/token.
 */
export async function promptGoogleAuth(): Promise<{
  id_token?: string;
  access_token?: string;
}> {
  const clientId = googleClientId();
  if (!clientId) {
    throw new Error("Google sign-in is not configured on this build.");
  }

  const redirectUri = AuthSession.makeRedirectUri({
    scheme: "gamesspot",
    path: "oauth",
  });

  const discovery = {
    authorizationEndpoint: "https://accounts.google.com/o/oauth2/v2/auth",
    tokenEndpoint: "https://oauth2.googleapis.com/token",
    revocationEndpoint: "https://oauth2.googleapis.com/revoke",
  };

  const request = new AuthSession.AuthRequest({
    clientId,
    redirectUri,
    scopes: ["openid", "profile", "email"],
    responseType: AuthSession.ResponseType.IdToken,
    usePKCE: true,
    extraParams: {
      nonce: await randomNonce(),
    },
  });

  const result = await request.promptAsync(discovery);
  if (result.type !== "success") {
    throw new Error("Google sign-in was cancelled.");
  }

  const idToken =
    (result.params as { id_token?: string }).id_token ||
    (result as { authentication?: { idToken?: string } }).authentication?.idToken;
  const accessToken =
    (result.params as { access_token?: string }).access_token ||
    (result as { authentication?: { accessToken?: string } }).authentication
      ?.accessToken;

  if (!idToken && !accessToken) {
    throw new Error("Google did not return a token.");
  }

  return { id_token: idToken, access_token: accessToken };
}

/**
 * Facebook: access token for Graph / Socialite userFromToken.
 */
export async function promptFacebookAuth(): Promise<{ access_token: string }> {
  const clientId = facebookAppId();
  if (!clientId) {
    throw new Error("Facebook sign-in is not configured on this build.");
  }

  const redirectUri = AuthSession.makeRedirectUri({
    scheme: "gamesspot",
    path: "oauth",
  });

  const discovery = {
    authorizationEndpoint: "https://www.facebook.com/v19.0/dialog/oauth",
    tokenEndpoint: "https://graph.facebook.com/v19.0/oauth/access_token",
  };

  const request = new AuthSession.AuthRequest({
    clientId,
    redirectUri,
    scopes: ["public_profile", "email"],
    responseType: AuthSession.ResponseType.Token,
  });

  const result = await request.promptAsync(discovery);
  if (result.type !== "success") {
    throw new Error("Facebook sign-in was cancelled.");
  }

  const accessToken =
    (result.params as { access_token?: string }).access_token ||
    (result as { authentication?: { accessToken?: string } }).authentication
      ?.accessToken;

  if (!accessToken) {
    throw new Error("Facebook did not return an access token.");
  }

  return { access_token: accessToken };
}

export async function promptSocialAuth(provider: SocialProvider) {
  if (provider === "google") {
    return promptGoogleAuth();
  }
  return promptFacebookAuth();
}
