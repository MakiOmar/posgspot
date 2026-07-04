import { $, component$, useSignal, useStore } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { MapPinIcon, PhoneIcon } from "~/components/icons";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import { ProtectedEmailLink } from "~/components/layout/protected-email-link";
import { ApiError, fetchLocations, fetchPhoneCountries, submitContactForm } from "~/lib/api";
import { toastError, toastSuccess } from "~/lib/notify";
import { validatePhone } from "~/lib/phone-validation";
import { usePendingState } from "~/lib/pending-context";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import type { StoreLocation } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useContactLocations = routeLoader$(async () => {
  try {
    const { data } = await fetchLocations();
    return data;
  } catch {
    return [] as StoreLocation[];
  }
});

export const useContactPhoneCountries = routeLoader$(async () => {
  try {
    const { data } = await fetchPhoneCountries();
    return data;
  } catch {
    return [];
  }
});

function mapEmbedUrl(locations: StoreLocation[]): string {
  const withCoords = locations.find((loc) => loc.latitude != null && loc.longitude != null);
  if (withCoords?.latitude != null && withCoords.longitude != null) {
    return `https://maps.google.com/maps?q=${withCoords.latitude},${withCoords.longitude}&z=14&output=embed`;
  }
  if (locations[0]?.address) {
    return `https://maps.google.com/maps?q=${encodeURIComponent(locations[0].address)}&z=14&output=embed`;
  }
  return "https://maps.google.com/maps?q=Cairo%2C%20Egypt&z=11&output=embed";
}

export default component$(() => {
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const locations = useContactLocations();
  const phoneCountries = useContactPhoneCountries();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const form = useStore({
    name: "",
    email: "",
    dialCode: "+20",
    nationalNumber: "",
    phone: "",
    message: "",
  });

  const phone = settings.value.contact?.phone || "";
  const phoneHref = phone.replace(/[^\d+]/g, "");
  const emailEncoded = settings.value.contact?.email_encoded || "";

  const submit$ = $(async () => {
    const phoneCheck = validatePhone(form.dialCode, form.nationalNumber, phoneCountries.value, locale);
    if (!phoneCheck.valid) {
      await toastError(phoneCheck.message);
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await submitContactForm({
          name: form.name.trim(),
          email: form.email.trim(),
          phone: phoneCheck.fullPhone,
          message: form.message.trim(),
        });
        form.name = "";
        form.email = "";
        form.dialCode = "+20";
        form.nationalNumber = "";
        form.phone = "";
        form.message = "";
        await toastSuccess(data.message);
      } catch (e) {
        const message =
          e instanceof ApiError
            ? e.message || tStatic(locale, "contact.sendFailed")
            : tStatic(locale, "contact.sendFailed");
        await toastError(message);
      }
    });
  });

  return (
    <article class="content-page contact-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <a href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</a>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "contact.title")}</span>
      </nav>

      <h1 class="content-title">{tStatic(locale, "contact.title")}</h1>

      <div class="contact-map-wrap">
        <iframe
          title={tStatic(locale, "contact.mapTitle")}
          class="contact-map"
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
          src={mapEmbedUrl(locations.value)}
        />
      </div>

      <div class="contact-cards">
        {emailEncoded ? (
          <div class="contact-card">
            <p class="contact-card-label">{tStatic(locale, "contact.emailAddress")}</p>
            <ProtectedEmailLink emailEncoded={emailEncoded} />
          </div>
        ) : null}

        {phone ? (
          <div class="contact-card">
            <p class="contact-card-label">{tStatic(locale, "contact.hotline")}</p>
            <p class="contact-card-value footer-contact">
              <PhoneIcon size={18} />
              <a href={`tel:${phoneHref}`}>{phone}</a>
            </p>
          </div>
        ) : null}

        <div class="contact-card contact-card--wide">
          <p class="contact-card-label">{tStatic(locale, "contact.ourBranches")}</p>
          {locations.value.length > 0 ? (
            <ul class="contact-branches">
              {locations.value.map((loc) => (
                <li key={loc.id}>
                  <strong>{loc.name}</strong>
                  {loc.address ? (
                    <span class="footer-contact">
                      <MapPinIcon size={16} />
                      {loc.maps_url ? (
                        <a href={loc.maps_url} target="_blank" rel="noopener noreferrer">
                          {loc.address}
                        </a>
                      ) : (
                        <span>{loc.address}</span>
                      )}
                    </span>
                  ) : null}
                  {loc.phone ? (
                    <span class="footer-contact">
                      <PhoneIcon size={16} />
                      <a href={`tel:${loc.phone.replace(/[^\d+]/g, "")}`}>{loc.phone}</a>
                    </span>
                  ) : null}
                </li>
              ))}
            </ul>
          ) : (
            <p class="footer-muted">{tStatic(locale, "contact.noBranches")}</p>
          )}
        </div>
      </div>

      <section class="contact-form-section">
        <h2 class="content-section-title">
          <span class="text-accent">{tStatic(locale, "contact.leaveUs")}</span> {tStatic(locale, "contact.aMessage")}
        </h2>

        <form class="contact-form" preventdefault:submit onSubmit$={submit$}>
          <div class="form-field form-field--left">
            <label for="contact-name">
              {tStatic(locale, "forms.name")} <span class="form-required" aria-hidden="true">*</span>
            </label>
            <input
              id="contact-name"
              type="text"
              name="name"
              required
              placeholder={tStatic(locale, "contact.namePlaceholder")}
              value={form.name}
              onInput$={(_, el) => (form.name = el.value)}
            />
          </div>
          <div class="form-field form-field--right">
            <label for="contact-email">
              {tStatic(locale, "forms.email")} <span class="form-required" aria-hidden="true">*</span>
            </label>
            <input
              id="contact-email"
              type="email"
              name="email"
              required
              placeholder={tStatic(locale, "contact.emailPlaceholder")}
              value={form.email}
              onInput$={(_, el) => (form.email = el.value)}
            />
          </div>
          <div class="form-field form-field--left">
            <label for="contact-phone">
              {tStatic(locale, "forms.phone")} <span class="form-required" aria-hidden="true">*</span>
            </label>
            <PhoneInputWithDialCode
              id="contact-phone"
              countries={phoneCountries.value}
              dialCode={form.dialCode}
              nationalNumber={form.nationalNumber}
              required
              onChange$={(value) => {
                form.dialCode = value.dialCode;
                form.nationalNumber = value.nationalNumber;
                form.phone = value.fullPhone;
              }}
            />
          </div>
          <div class="form-field form-field--right contact-form-message">
            <label for="contact-message">
              {tStatic(locale, "forms.yourMessage")} <span class="form-required" aria-hidden="true">*</span>
            </label>
            <textarea
              id="contact-message"
              name="message"
              rows={5}
              required
              placeholder={tStatic(locale, "contact.messagePlaceholder")}
              value={form.message}
              onInput$={(_, el) => (form.message = el.value)}
            />
          </div>
          <div class="form-actions form-actions--left">
            <button type="submit" class="btn btn-primary" disabled={submitting.value}>
              {submitting.value ? tStatic(locale, "contact.sending") : tStatic(locale, "contact.sendMessage")}
            </button>
          </div>
        </form>
      </section>
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const title = tStatic(lang, "contact.seoTitle", { businessName: settings.business_name });
  const description = tStatic(lang, "contact.seoDescription", { businessName: settings.business_name });

  return withStorefrontThemeHead(
    {
      title,
      meta: [
        { name: "description", content: description },
        { property: "og:title", content: title },
        { property: "og:description", content: description },
        { property: "og:type", content: "website" },
        { name: "twitter:card", content: "summary" },
      ],
      links: publicSeoLinks(url.origin, "/contact", lang),
    },
    settings,
  );
};
