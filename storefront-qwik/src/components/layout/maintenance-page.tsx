import { component$ } from "@builder.io/qwik";
import { PhoneIcon } from "~/components/icons";
import { LanguageSwitcher } from "~/components/layout/language-switcher";
import { ProtectedEmailLink } from "~/components/layout/protected-email-link";
import { tStatic, useI18n } from "~/lib/i18n/context";
import type { StoreSettings } from "~/lib/types";

interface MaintenancePageProps {
  settings: StoreSettings;
}

export const MaintenancePage = component$<MaintenancePageProps>(({ settings }) => {
  const { locale } = useI18n();
  const name = settings.business_name;

  return (
    <div class="maintenance-page" dir={locale === "ar" ? "rtl" : "ltr"} lang={locale}>
      <header class="maintenance-page__top">
        <LanguageSwitcher settings={settings} />
      </header>

      <main class="maintenance-page__main">
        <div class="maintenance-card">
          {settings.logo_url ? (
            <img
              class="maintenance-card__logo"
              src={settings.logo_url}
              alt={name}
              width={160}
              height={48}
            />
          ) : (
            <p class="maintenance-card__brand">{name}</p>
          )}

          <p class="maintenance-card__kicker">{tStatic(locale, "maintenance.kicker")}</p>
          <h1 class="maintenance-card__title">{tStatic(locale, "maintenance.heading")}</h1>
          <p class="maintenance-card__body">{tStatic(locale, "maintenance.body")}</p>

          {settings.contact.phone || settings.contact.email_encoded ? (
            <div class="maintenance-card__contact">
              <p class="maintenance-card__contact-label">
                {tStatic(locale, "maintenance.contactLabel")}
              </p>
              {settings.contact.phone ? (
                <p class="maintenance-card__contact-item">
                  <PhoneIcon size={16} />
                  <a href={`tel:${settings.contact.phone}`} dir="ltr">
                    {settings.contact.phone}
                  </a>
                </p>
              ) : null}
              {settings.contact.email_encoded ? (
                <ProtectedEmailLink emailEncoded={settings.contact.email_encoded} />
              ) : null}
            </div>
          ) : null}
        </div>
      </main>
    </div>
  );
});
