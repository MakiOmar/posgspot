import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import {
  ApiError,
  fetchAccountDeviceServices,
  lookupDeviceTrack,
  type DeviceTrackService,
} from "~/lib/api";
import { isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/[lang]/layout";

const DeviceServiceCards = component$<{ services: DeviceTrackService[] }>(({ services }) => {
  const { locale } = useI18n();

  return (
    <div class="repair-status-results">
      {services.map((service) => (
        <section key={service.tracking_code || String(service.id)} class="repair-status-card">
          <header class="repair-status-card__head">
            <h2>{service.tracking_code || tStatic(locale, "trackConsole.result")}</h2>
            {service.status_display || service.status ? (
              <span class={`repair-status-badge track-console-badge track-console-badge--${service.status}`}>
                {service.status_display || service.status}
              </span>
            ) : null}
          </header>

          <dl class="repair-status-meta">
            {service.device_model?.full_name || service.device_model?.name ? (
              <>
                <dt>{tStatic(locale, "trackConsole.device")}</dt>
                <dd>{service.device_model.full_name || service.device_model.name}</dd>
              </>
            ) : null}
            {service.device_serial_number ? (
              <>
                <dt>{tStatic(locale, "trackConsole.serialNo")}</dt>
                <dd>{service.device_serial_number}</dd>
              </>
            ) : null}
            {service.store_profile?.name ? (
              <>
                <dt>{tStatic(locale, "trackConsole.store")}</dt>
                <dd>{service.store_profile.name}</dd>
              </>
            ) : null}
            {service.submitted_at ? (
              <>
                <dt>{tStatic(locale, "trackConsole.submitted")}</dt>
                <dd>{service.submitted_at}</dd>
              </>
            ) : null}
            {service.status_updated_at ? (
              <>
                <dt>{tStatic(locale, "trackConsole.updated")}</dt>
                <dd>{service.status_updated_at}</dd>
              </>
            ) : null}
            {service.notes ? (
              <>
                <dt>{tStatic(locale, "trackConsole.notes")}</dt>
                <dd>{service.notes}</dd>
              </>
            ) : null}
          </dl>
        </section>
      ))}
    </div>
  );
});

export default component$(() => {
  const auth = useAuth();
  const { locale } = useI18n();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const services = useSignal<DeviceTrackService[]>([]);
  const searched = useSignal(false);
  const myServices = useSignal<DeviceTrackService[]>([]);
  const myLoaded = useSignal(false);
  const myLoading = useSignal(false);
  const signedIn = isAuthenticated(auth);

  const form = useStore({
    phone_number: "",
  });

  // Auto-load signed-in customer's console services from account phone.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (!auth.ready) {
      return;
    }
    if (!auth.token) {
      myServices.value = [];
      myLoaded.value = true;
      myLoading.value = false;
      return;
    }

    myLoading.value = true;
    try {
      const { data } = await fetchAccountDeviceServices(auth.token, locale);
      myServices.value = data.services ?? [];
    } catch {
      myServices.value = [];
    } finally {
      myLoading.value = false;
      myLoaded.value = true;
    }
  });

  const submit$ = $(async () => {
    const phone = form.phone_number.trim();
    if (!phone) {
      await toastError(tStatic(locale, "trackConsole.phoneRequired"));
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await lookupDeviceTrack({ phone_number: phone }, locale);
        services.value = data.services ?? [];
        searched.value = true;
        await toastSuccess(tStatic(locale, "trackConsole.found"));
      } catch (e) {
        services.value = [];
        searched.value = true;
        const message =
          e instanceof ApiError
            ? e.message || tStatic(locale, "trackConsole.notFound")
            : tStatic(locale, "trackConsole.notFound");
        await toastError(message);
      }
    });
  });

  return (
    <div class="track-console-layout-page">
      <PageTitleBar
        title={tStatic(locale, "trackConsole.title")}
        crumbs={[{ label: tStatic(locale, "nav.trackConsole") }]}
      />
      <article class="content-page repair-status-page track-console-page">
      {/* Guest-only intro; signed-in customers see their list instead of lookup copy. */}
      {auth.ready && !signedIn ? (
        <p class="content-lead">{tStatic(locale, "trackConsole.intro")}</p>
      ) : null}

      {/* Signed-in: auto-list only. Wait for auth so the guest form does not flash. */}
      {signedIn || !auth.ready ? (
        <section class="repair-status-mine" aria-live="polite">
          <h2 class="repair-status-mine__title">{tStatic(locale, "trackConsole.myServices")}</h2>
          {!auth.ready || myLoading.value ? (
            <p class="footer-muted">{tStatic(locale, "trackConsole.loadingMine")}</p>
          ) : myLoaded.value && myServices.value.length === 0 ? (
            <p class="footer-muted">{tStatic(locale, "trackConsole.myServicesEmpty")}</p>
          ) : myServices.value.length > 0 ? (
            <DeviceServiceCards services={myServices.value} />
          ) : null}
        </section>
      ) : null}

      {/* Guests only: phone lookup. */}
      {auth.ready && !signedIn ? (
        <div class="repair-status-layout">
          <div class="repair-status-layout__form">
            <p class="footer-muted">
              {tStatic(locale, "trackConsole.myServicesSignIn")}{" "}
              <Link href={localePath(locale, "/login")}>{tStatic(locale, "header.signIn")}</Link>
            </p>
            <form class="repair-status-form" preventdefault:submit onSubmit$={submit$}>
              <div>
                <label for="track_console_phone">{tStatic(locale, "trackConsole.phone")}</label>
                <input
                  id="track_console_phone"
                  type="tel"
                  required
                  autocomplete="tel"
                  value={form.phone_number}
                  placeholder={tStatic(locale, "trackConsole.phonePlaceholder")}
                  onInput$={(_, el) => {
                    form.phone_number = el.value;
                  }}
                />
              </div>

              <button type="submit" class="btn btn-primary" disabled={submitting.value}>
                {submitting.value
                  ? tStatic(locale, "trackConsole.searching")
                  : tStatic(locale, "trackConsole.search")}
              </button>
            </form>
          </div>

          <div class="repair-status-layout__results" aria-live="polite">
            {searched.value && services.value.length === 0 ? (
              <p class="footer-muted repair-status-empty">{tStatic(locale, "trackConsole.notFound")}</p>
            ) : null}

            {services.value.length > 0 ? <DeviceServiceCards services={services.value} /> : null}
          </div>
        </div>
      ) : null}
      </article>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = tStatic(lang, "trackConsole.seoTitle").replace("{businessName}", settings.business_name);
  const description = tStatic(lang, "trackConsole.seoDescription").replace(
    "{businessName}",
    settings.business_name,
  );

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { property: "og:url", content: url.href },
        { name: "twitter:card", content: "summary" },
        { name: "twitter:title", content: title },
        { name: "twitter:description", content: description },
      ],
      links: publicSeoLinks(url.origin, "/track-console", lang),
    },
    settings,
  );
};
