import { component$, useId, useSignal, useVisibleTask$, type Signal } from "@builder.io/qwik";

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

export type TurnstileActivation = "immediate" | "visible" | "interaction";

function getTurnstile(): TurnstileApi | undefined {
  return (window as Window & { turnstile?: TurnstileApi }).turnstile;
}

function loadTurnstileScript(): Promise<void> {
  const existing = document.querySelector(
    `script[src="${TURNSTILE_SCRIPT}"]`,
  ) as HTMLScriptElement | null;
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
  /**
   * When to load the third-party script:
   * - `immediate` — on hydrate (auth / contact forms)
   * - `visible` — when the widget scrolls near the viewport
   * - `interaction` — after focus/pointer on a related control (footer newsletter)
   */
  activation?: TurnstileActivation;
  /** Required for `activation="interaction"` — element id that unlocks the widget. */
  unlockOnFocusId?: string;
}

/**
 * Cloudflare Turnstile widget — loads only in the browser when a site key is configured.
 * Deferred activation avoids third-party cookies / main-thread work on first paint.
 */
export const TurnstileWidget = component$<TurnstileWidgetProps>((props) => {
  const containerId = useId();
  const unlocked = useSignal(props.activation !== "interaction");

  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track, cleanup }) => {
    track(() => props.siteKey);
    track(() => props.resetKey ?? 0);
    track(() => unlocked.value);

    const activation: TurnstileActivation = props.activation ?? "immediate";
    let widgetId: string | undefined;
    let observer: IntersectionObserver | undefined;
    let cancelled = false;

    const renderWidget = () => {
      if (cancelled) {
        return;
      }
      const turnstile = getTurnstile();
      const container = document.getElementById(containerId);
      if (!turnstile || !container) {
        return;
      }

      container.innerHTML = "";
      props.token.value = "";
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

    const startLoad = () => {
      loadTurnstileScript()
        .then(renderWidget)
        .catch(() => {
          props.token.value = "";
        });
    };

    const tearDownWidget = () => {
      const turnstile = getTurnstile();
      if (widgetId && turnstile?.remove) {
        turnstile.remove(widgetId);
      }
      widgetId = undefined;
      props.token.value = "";
    };

    if (activation === "interaction") {
      const unlockId = props.unlockOnFocusId;
      if (!unlockId) {
        return;
      }
      const onUnlock = () => {
        unlocked.value = true;
      };
      const target = document.getElementById(unlockId);
      target?.addEventListener("focus", onUnlock, { once: true });
      target?.addEventListener("pointerdown", onUnlock, { once: true });

      cleanup(() => {
        cancelled = true;
        target?.removeEventListener("focus", onUnlock);
        target?.removeEventListener("pointerdown", onUnlock);
        tearDownWidget();
      });

      if (!unlocked.value) {
        return;
      }
      startLoad();
      return;
    }

    if (activation === "visible") {
      const container = document.getElementById(containerId);
      if (!container || typeof IntersectionObserver === "undefined") {
        startLoad();
      } else {
        observer = new IntersectionObserver(
          (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
              observer?.disconnect();
              observer = undefined;
              startLoad();
            }
          },
          { rootMargin: "200px 0px", threshold: 0.01 },
        );
        observer.observe(container);
      }

      cleanup(() => {
        cancelled = true;
        observer?.disconnect();
        tearDownWidget();
      });
      return;
    }

    startLoad();
    cleanup(() => {
      cancelled = true;
      tearDownWidget();
    });
  });

  return <div id={containerId} class="turnstile-widget" aria-label="Security check" />;
});
