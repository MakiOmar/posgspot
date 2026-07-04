import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import { fetchPhoneCountries, updateAddress, updateProfile } from "~/lib/api";
import { useAuth } from "~/lib/auth-context";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { toastError, toastSuccess } from "~/lib/notify";
import { parseFullPhone, validatePhone } from "~/lib/phone-validation";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";
import type { AuthContact } from "~/lib/types";
import { useLangParam } from "~/routes/[lang]/layout";

interface ProfileForm {
  first_name: string;
  last_name: string;
  email: string;
  dialCode: string;
  nationalNumber: string;
  mobile: string;
  address_line_1: string;
  address_line_2: string;
  city: string;
  state: string;
  country: string;
  zip_code: string;
}

function formFromContact(c: AuthContact | null, dialCode: string, nationalNumber: string): ProfileForm {
  return {
    first_name: c?.first_name || "",
    last_name: c?.last_name || "",
    email: c?.email || "",
    dialCode,
    nationalNumber,
    mobile: c?.mobile || "",
    address_line_1: c?.address_line_1 || "",
    address_line_2: c?.address_line_2 || "",
    city: c?.city || "",
    state: c?.state || "",
    country: c?.country || "",
    zip_code: c?.zip_code || "",
  };
}

export const useProfilePhoneCountries = routeLoader$(async () => {
  try {
    const { data } = await fetchPhoneCountries();
    return data;
  } catch {
    return [];
  }
});

export default component$(() => {
  const auth = useAuth();
  const { locale } = useI18n();
  const phoneCountries = useProfilePhoneCountries();
  const form = useStore<ProfileForm>(formFromContact(null, "+20", ""));
  const saving = useSignal(false);
  const pending = usePendingState();
  const phoneReady = useSignal(false);

  // Prefill from the cached contact once hydrated.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.contact);
    const parsed = parseFullPhone(auth.contact?.mobile || "", phoneCountries.value);
    Object.assign(form, formFromContact(auth.contact, parsed.dialCode, parsed.nationalNumber));
    phoneReady.value = true;
  });

  const save$ = $(async () => {
    const token = auth.token;
    if (!token) {
      return;
    }

    const phoneCheck = validatePhone(form.dialCode, form.nationalNumber, phoneCountries.value, locale);
    if (!phoneCheck.valid) {
      await toastError(phoneCheck.message);
      return;
    }

    await withPendingFeedback(pending, saving, async () => {
      try {
        await updateProfile(token, {
          first_name: form.first_name,
          last_name: form.last_name,
          email: form.email,
          mobile: phoneCheck.fullPhone,
        });
        const { data } = await updateAddress(token, {
          address_line_1: form.address_line_1,
          address_line_2: form.address_line_2,
          city: form.city,
          state: form.state,
          country: form.country,
          zip_code: form.zip_code,
        });
        auth.contact = data;
        await toastSuccess(tStatic(locale, "account.saved"));
      } catch {
        await toastError(tStatic(locale, "account.saveFailed"));
      }
    });
  });

  return (
    <div>
      <h1 class="page-title">{tStatic(locale, "account.profileAddress")}</h1>

      <form preventdefault:submit onSubmit$={save$} class="account-form">
        <h2>{tStatic(locale, "account.personalDetails")}</h2>
        <div class="form-grid">
          <div class="form-field">
            <label for="first_name">{tStatic(locale, "forms.firstName")}</label>
            <input id="first_name" type="text" value={form.first_name} onInput$={(_, el) => (form.first_name = el.value)} />
          </div>
          <div class="form-field">
            <label for="last_name">{tStatic(locale, "forms.lastName")}</label>
            <input id="last_name" type="text" value={form.last_name} onInput$={(_, el) => (form.last_name = el.value)} />
          </div>
          <div class="form-field">
            <label for="email">{tStatic(locale, "forms.email")}</label>
            <input id="email" type="email" value={form.email} onInput$={(_, el) => (form.email = el.value)} />
          </div>
          <div class="form-field">
            <label for="profile-mobile">{tStatic(locale, "forms.mobile")}</label>
            {phoneReady.value ? (
              <PhoneInputWithDialCode
                id="profile-mobile"
                countries={phoneCountries.value}
                dialCode={form.dialCode}
                nationalNumber={form.nationalNumber}
                onChange$={(value) => {
                  form.dialCode = value.dialCode;
                  form.nationalNumber = value.nationalNumber;
                  form.mobile = value.fullPhone;
                }}
              />
            ) : null}
          </div>
        </div>

        <h2 style={{ marginTop: "1.5rem" }}>{tStatic(locale, "account.deliveryAddress")}</h2>
        <div class="form-grid">
          <div class="form-field form-field--full">
            <label for="address_line_1">{tStatic(locale, "forms.addressLine1")}</label>
            <input id="address_line_1" type="text" value={form.address_line_1} onInput$={(_, el) => (form.address_line_1 = el.value)} />
          </div>
          <div class="form-field form-field--full">
            <label for="address_line_2">{tStatic(locale, "forms.addressLine2")}</label>
            <input id="address_line_2" type="text" value={form.address_line_2} onInput$={(_, el) => (form.address_line_2 = el.value)} />
          </div>
          <div class="form-field">
            <label for="city">{tStatic(locale, "forms.city")}</label>
            <input id="city" type="text" value={form.city} onInput$={(_, el) => (form.city = el.value)} />
          </div>
          <div class="form-field">
            <label for="state">{tStatic(locale, "forms.state")}</label>
            <input id="state" type="text" value={form.state} onInput$={(_, el) => (form.state = el.value)} />
          </div>
          <div class="form-field">
            <label for="country">{tStatic(locale, "forms.country")}</label>
            <input id="country" type="text" value={form.country} onInput$={(_, el) => (form.country = el.value)} />
          </div>
          <div class="form-field">
            <label for="zip_code">{tStatic(locale, "forms.postalCode")}</label>
            <input id="zip_code" type="text" value={form.zip_code} onInput$={(_, el) => (form.zip_code = el.value)} />
          </div>
        </div>

        <div style={{ marginTop: "1.5rem" }}>
          <button type="submit" class="btn btn-primary" disabled={saving.value}>
            {saving.value ? tStatic(locale, "account.saving") : tStatic(locale, "account.saveChanges")}
          </button>
        </div>
      </form>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue }) => {
  const lang = resolveValue(useLangParam);
  return {
    title: tStatic(lang, "account.profileAddress"),
    meta: [{ name: "robots", content: "noindex, nofollow" }],
  };
};
