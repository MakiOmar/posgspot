import { useCallback, useMemo, useState } from "react";
import { Linking, StyleSheet, View } from "react-native";
import { WebView, type WebViewMessageEvent } from "react-native-webview";
import type { ShouldStartLoadRequest } from "react-native-webview/lib/WebViewTypes";
import { useApp } from "../../contexts/AppContext";
import { STOREFRONT_WEB_URL } from "../../lib/config";
import { toast } from "../../lib/toast";

const CHALLENGE_HOST = "https://challenges.cloudflare.com";

export interface TurnstileState {
  enabled: boolean;
  siteKey: string;
  token: string;
  resetKey: number;
  setToken: (token: string) => void;
  /** Remount the widget for a fresh token (tokens are single-use). */
  reset: () => void;
  /** Toasts and returns false when the check is enabled but not completed. */
  ensure: () => boolean;
  /** `{ turnstile_token }` when enabled, otherwise `{}` — spread into request bodies. */
  payload: () => { turnstile_token?: string };
}

/** Cloudflare Turnstile state for a form; enabled when settings expose a site key (same as the website). */
export function useTurnstile(): TurnstileState {
  const { settings, t } = useApp();
  const config = settings?.turnstile;
  const siteKey = config?.enabled ? (config.site_key || "").trim() : "";
  const enabled = siteKey !== "";
  const [token, setToken] = useState("");
  const [resetKey, setResetKey] = useState(0);

  const reset = useCallback(() => {
    setToken("");
    setResetKey((key) => key + 1);
  }, []);

  const ensure = useCallback(() => {
    if (enabled && !token) {
      toast.error(t("turnstile.required"));
      return false;
    }
    return true;
  }, [enabled, t, token]);

  const payload = useCallback(
    () => (enabled && token ? { turnstile_token: token } : {}),
    [enabled, token],
  );

  return { enabled, siteKey, token, resetKey, setToken, reset, ensure, payload };
}

function widgetHtml(siteKey: string, language: string): string {
  return `<!doctype html><html><head>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<style>html,body{margin:0;padding:0;background:transparent;overflow:hidden}#ts{display:flex;justify-content:center}</style>
<script>
function post(m){window.ReactNativeWebView&&window.ReactNativeWebView.postMessage(JSON.stringify(m));}
function onTurnstileLoad(){
  window.turnstile.render('#ts',{
    sitekey:${JSON.stringify(siteKey)},
    language:${JSON.stringify(language)},
    size:'flexible',
    callback:function(t){post({type:'token',token:t});},
    'expired-callback':function(){post({type:'expired'});},
    'error-callback':function(){post({type:'error'});}
  });
}
</script>
<script src="${CHALLENGE_HOST}/turnstile/v0/api.js?render=explicit&onload=onTurnstileLoad" async defer></script>
</head><body><div id="ts"></div></body></html>`;
}

/**
 * Turnstile in a WebView whose `baseUrl` is the storefront origin, so the site key's
 * hostname allowlist matches the website. Renders nothing when Turnstile is off.
 */
export function TurnstileWidget({ turnstile }: { turnstile: TurnstileState }) {
  const { locale, t } = useApp();
  const { enabled, siteKey, resetKey, setToken } = turnstile;
  const html = useMemo(() => widgetHtml(siteKey, locale), [siteKey, locale]);

  const onMessage = useCallback(
    (event: WebViewMessageEvent) => {
      let message: { type?: string; token?: string };
      try {
        message = JSON.parse(event.nativeEvent.data) as typeof message;
      } catch {
        return;
      }
      if (message.type === "token" && message.token) {
        setToken(message.token);
        return;
      }
      setToken("");
      if (message.type === "error") {
        toast.error(t("turnstile.failed"));
      }
    },
    [setToken, t],
  );

  const onShouldStartLoad = useCallback((request: ShouldStartLoadRequest) => {
    const url = request.url;
    if (
      request.isTopFrame === false ||
      url.startsWith("about:") ||
      url.startsWith(CHALLENGE_HOST) ||
      url.startsWith(STOREFRONT_WEB_URL)
    ) {
      return true;
    }
    if (/^https?:\/\//i.test(url)) {
      void Linking.openURL(url);
    }
    return false;
  }, []);

  if (!enabled) {
    return null;
  }

  return (
    <View
      style={styles.wrap}
      accessible
      accessibilityLabel={t("turnstile.label")}
    >
      <WebView
        key={`${siteKey}-${resetKey}`}
        source={{ html, baseUrl: STOREFRONT_WEB_URL }}
        originWhitelist={["*"]}
        onMessage={onMessage}
        onShouldStartLoadWithRequest={onShouldStartLoad}
        setSupportMultipleWindows={false}
        scrollEnabled={false}
        javaScriptEnabled
        style={styles.webview}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { height: 72, marginVertical: 8, overflow: "hidden" },
  webview: { flex: 1, backgroundColor: "transparent" },
});
