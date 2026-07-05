import { component$, useId, useVisibleTask$, type Signal } from "@builder.io/qwik";

const TURNSTILE_SCRIPT = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";

type TurnstileApi = {
  render: (
    selector: string,
    options: {
      sitekey: string;
      callback: (token: string) => void;
      "expired-callback"?: () => void;
      "error-callback"?: () => void;
    },
  ) => string;
  remove: (widgetId: string) => void;
};

function getTurnstile(): TurnstileApi | undefined {
  return (window as Window & { turnstile?: TurnstileApi }).turnstile;
}

function loadTurnstileScript(): Promise<void> {
  const existing = document.querySelector(`script[src="${TURNSTILE_SCRIPT}"]`) as HTMLScriptElement | null;
  if (getTurnstile()) {
    return Promise.resolve();
  }
  if (existing) {
    return new Promise((resolve) => {
      if (getTurnstile()) {
        resolve();
        return;
      }
      existing.addEventListener("load", () => resolve(), { once: true });
    });
  }

  return new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = TURNSTILE_SCRIPT;
    script.async = true;
    script.defer = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Turnstile script failed to load"));
    document.head.appendChild(script);
  });
}

export interface TurnstileWidgetProps {
  siteKey: string;
  token: Signal<string>;
  /** Increment to remount the widget (e.g. after a successful form submit). */
  resetKey?: number;
}

/** Cloudflare Turnstile widget — loads only in the browser when a site key is configured. */
export const TurnstileWidget = component$<TurnstileWidgetProps>((props) => {
  const containerId = useId();

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track, cleanup }) => {
    track(() => props.siteKey);
    track(() => props.resetKey ?? 0);

    let widgetId: string | undefined;
    props.token.value = "";

    const renderWidget = () => {
      const turnstile = getTurnstile();
      const container = document.getElementById(containerId);
      if (!turnstile || !container) {
        return;
      }

      container.innerHTML = "";
      widgetId = turnstile.render(`#${containerId}`, {
        sitekey: props.siteKey,
        callback: (token: string) => {
          props.token.value = token;
        },
        "expired-callback": () => {
          props.token.value = "";
        },
        "error-callback": () => {
          props.token.value = "";
        },
      });
    };

    loadTurnstileScript()
      .then(renderWidget)
      .catch(() => {
        props.token.value = "";
      });

    cleanup(() => {
      const turnstile = getTurnstile();
      if (widgetId && turnstile?.remove) {
        turnstile.remove(widgetId);
      }
      props.token.value = "";
    });
  });

  return <div id={containerId} class="turnstile-widget" aria-label="Security check" />;
});
