import { component$ } from "@builder.io/qwik";
import { routeLoader$ } from "@builder.io/qwik-city";
import { detectPreferredLocale } from "~/lib/i18n/paths";

/** Redirect bare / to the default or preferred locale prefix. */
export const useRootRedirect = routeLoader$(({ redirect, request }) => {
  const preferred = detectPreferredLocale(request.headers.get("accept-language"));
  throw redirect(302, `/${preferred}/`);
});

export default component$(() => {
  return null;
});
