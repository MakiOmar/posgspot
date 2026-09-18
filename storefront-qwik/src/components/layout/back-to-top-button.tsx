import { $, component$, useOnWindow, useSignal } from "@builder.io/qwik";
import { ChevronUpIcon } from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";

/** Floating control that scrolls smoothly back to the top after the user scrolls down. */
export const BackToTopButton = component$(() => {
  const { locale } = useI18n();
  const visible = useSignal(false);

  useOnWindow(
    "scroll",
    $(() => {
      visible.value = window.scrollY > 480;
    }),
  );

  const scrollTop$ = $(() => {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    window.scrollTo({ top: 0, behavior: reduceMotion ? "auto" : "smooth" });
  });

  return (
    <button
      type="button"
      class={`back-to-top${visible.value ? " is-visible" : ""}`}
      aria-label={tStatic(locale, "common.backToTop")}
      aria-hidden={!visible.value}
      tabIndex={visible.value ? 0 : -1}
      onClick$={scrollTop$}
    >
      <ChevronUpIcon size={22} />
    </button>
  );
});
