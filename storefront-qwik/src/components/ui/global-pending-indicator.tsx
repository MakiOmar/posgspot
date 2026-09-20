import { component$, useVisibleTask$ } from "@builder.io/qwik";
import { useLocation } from "@builder.io/qwik-city";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { usePendingState } from "~/lib/pending-context";
import { useSiteShell } from "~/lib/site-shell-context";

const STAGE = 260;
const CENTER = STAGE / 2;
const OUTER_R = 118;
const INNER_R = 98;

function arcDash(radius: number): string {
  const circumference = 2 * Math.PI * radius;
  const arc = circumference * 0.75;
  return `${arc} ${circumference}`;
}

/**
 * Full-screen page-transition / async loader matching Expo BrandSplash:
 * settings logo centered with two opposite-rotating ¾ arcs on black.
 */
export const GlobalPendingIndicator = component$(() => {
  const loc = useLocation();
  const pending = usePendingState();
  const shell = useSiteShell();
  const { locale } = useI18n();
  const isActive = loc.isNavigating || pending.clientCount > 0;
  // POS Business Settings logo only — never the storefront Appearance override.
  const logoUrl = shell.settings.business_logo_url || "";
  const businessName = shell.settings.business_name || "Games Spot";

  // Sync body busy state for assistive tech (DOM-only; needs client).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => loc.isNavigating);
    track(() => pending.clientCount);
    const active = loc.isNavigating || pending.clientCount > 0;
    document.body.classList.toggle("is-globally-pending", active);
    document.body.setAttribute("aria-busy", active ? "true" : "false");
  });

  return (
    <div
      class={`brand-nav-splash${isActive ? " brand-nav-splash--active" : ""}`}
      role="status"
      aria-live="polite"
      aria-busy={isActive}
      aria-hidden={!isActive}
    >
      <div class="brand-nav-splash__stage" aria-hidden="true">
        {/* Outer ring — clockwise */}
        <svg
          class="brand-nav-splash__ring brand-nav-splash__ring--outer"
          width={STAGE}
          height={STAGE}
          viewBox={`0 0 ${STAGE} ${STAGE}`}
        >
          <circle
            cx={CENTER}
            cy={CENTER}
            r={OUTER_R}
            fill="none"
            stroke="currentColor"
            stroke-width="3.5"
            stroke-linecap="round"
            stroke-dasharray={arcDash(OUTER_R)}
          />
        </svg>
        {/* Inner ring — counter-clockwise */}
        <svg
          class="brand-nav-splash__ring brand-nav-splash__ring--inner"
          width={STAGE}
          height={STAGE}
          viewBox={`0 0 ${STAGE} ${STAGE}`}
        >
          <circle
            cx={CENTER}
            cy={CENTER}
            r={INNER_R}
            fill="none"
            stroke="currentColor"
            stroke-width="2.75"
            stroke-linecap="round"
            stroke-dasharray={arcDash(INNER_R)}
          />
        </svg>
        {logoUrl ? (
          <img
            class="brand-nav-splash__logo"
            src={logoUrl}
            alt=""
            width={143}
            height={143}
            decoding="async"
          />
        ) : (
          <span class="brand-nav-splash__wordmark">{businessName}</span>
        )}
      </div>
      <span class="sr-only">
        {isActive ? tStatic(locale, "common.loadingPleaseWait") : ""}
      </span>
    </div>
  );
});
