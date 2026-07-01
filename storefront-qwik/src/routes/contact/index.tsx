import { $, component$, useSignal, useStore } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { MapPinIcon, PhoneIcon } from "~/components/icons";
import { ProtectedEmailLink } from "~/components/layout/protected-email-link";
import { ApiError, fetchLocations, submitContactForm } from "~/lib/api";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { StoreLocation } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/layout";

export const useContactLocations = routeLoader$(async () => {
  try {
    const { data } = await fetchLocations();
    return data;
  } catch {
    return [] as StoreLocation[];
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
  const locations = useContactLocations();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const form = useStore({
    name: "",
    email: "",
    phone: "",
    message: "",
  });

  const phone = settings.value.contact?.phone || "";
  const phoneHref = phone.replace(/[^\d+]/g, "");
  const emailEncoded = settings.value.contact?.email_encoded || "";

  const submit$ = $(async () => {
    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await submitContactForm({
          name: form.name.trim(),
          email: form.email.trim(),
          phone: form.phone.trim(),
          message: form.message.trim(),
        });
        form.name = "";
        form.email = "";
        form.phone = "";
        form.message = "";
        await toastSuccess(data.message);
      } catch (e) {
        const message =
          e instanceof ApiError
            ? e.message || "Could not send your message. Please try again."
            : "Could not send your message. Please try again.";
        await toastError(message);
      }
    });
  });

  return (
    <article class="content-page contact-page">
      <nav class="content-breadcrumb" aria-label="Breadcrumb">
        <a href="/">Home</a>
        <span aria-hidden="true">›</span>
        <span>Contact</span>
      </nav>

      <h1 class="content-title">Contact</h1>

      <div class="contact-map-wrap">
        <iframe
          title="Games Spot store locations map"
          class="contact-map"
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
          src={mapEmbedUrl(locations.value)}
        />
      </div>

      <div class="contact-cards">
        {emailEncoded ? (
          <div class="contact-card">
            <p class="contact-card-label">Email address</p>
            <ProtectedEmailLink emailEncoded={emailEncoded} />
          </div>
        ) : null}

        {phone ? (
          <div class="contact-card">
            <p class="contact-card-label">Hotline</p>
            <p class="contact-card-value footer-contact">
              <PhoneIcon size={18} />
              <a href={`tel:${phoneHref}`}>{phone}</a>
            </p>
          </div>
        ) : null}

        <div class="contact-card contact-card--wide">
          <p class="contact-card-label">Our branches</p>
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
            <p class="footer-muted">Branch addresses will appear here once locations are configured.</p>
          )}
        </div>
      </div>

      <section class="contact-form-section">
        <h2 class="content-section-title">
          <span class="text-accent">Leave us</span> a message
        </h2>

        <form class="contact-form" preventdefault:submit onSubmit$={submit$}>
          <div class="form-field">
            <label for="contact-name">Name *</label>
            <input
              id="contact-name"
              type="text"
              name="name"
              required
              value={form.name}
              onInput$={(_, el) => (form.name = el.value)}
            />
          </div>
          <div class="form-field">
            <label for="contact-email">Email *</label>
            <input
              id="contact-email"
              type="email"
              name="email"
              required
              value={form.email}
              onInput$={(_, el) => (form.email = el.value)}
            />
          </div>
          <div class="form-field">
            <label for="contact-phone">Phone *</label>
            <input
              id="contact-phone"
              type="tel"
              name="phone"
              required
              value={form.phone}
              onInput$={(_, el) => (form.phone = el.value)}
            />
          </div>
          <div class="form-field form-field--full">
            <label for="contact-message">Your message *</label>
            <textarea
              id="contact-message"
              name="message"
              rows={5}
              required
              value={form.message}
              onInput$={(_, el) => (form.message = el.value)}
            />
          </div>
          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? "Sending…" : "Send message"}
          </button>
        </form>
      </section>
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const settings = resolveValue(useSiteSettings);
  const title = `Contact — ${settings.business_name}`;
  const description = `Contact ${settings.business_name}—call, email, or visit our branches across Egypt.`;

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
    },
    settings,
  );
};
