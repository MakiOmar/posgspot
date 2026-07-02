import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { SearchableSelect } from "~/components/forms/searchable-select";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import {
  addCustomer,
  ApiError,
  fetchGeoCountries,
  fetchGeoStates,
  fetchPhoneCountries,
} from "~/lib/api";
import { toastError, toastSuccess } from "~/lib/notify";
import type { GeoState } from "~/lib/phone-validation";
import { validatePhone } from "~/lib/phone-validation";
import { usePendingState } from "~/lib/pending-context";
import { withPendingFeedback } from "~/lib/with-pending";

export const usePhoneCountries = routeLoader$(async () => {
  try {
    const { data } = await fetchPhoneCountries();
    return data;
  } catch {
    return [];
  }
});

export const useGeoCountries = routeLoader$(async () => {
  try {
    const { data } = await fetchGeoCountries();
    return data;
  } catch {
    return [];
  }
});

export default component$(() => {
  const phoneCountries = usePhoneCountries();
  const geoCountries = useGeoCountries();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const succeeded = useSignal(false);
  const successMessage = useSignal("");
  const states = useSignal<GeoState[]>([]);
  const statesLoading = useSignal(false);

  const form = useStore({
    first_name: "",
    last_name: "",
    email: "",
    birth_date: "",
    country: "EG",
    state: "",
    dialCode: "+20",
    nationalNumber: "",
    mobile: "",
  });

  // Load states when country changes.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => form.country);
    if (!form.country) {
      states.value = [];
      form.state = "";
      return;
    }
    statesLoading.value = true;
    try {
      const { data } = await fetchGeoStates(form.country);
      states.value = data;
      if (!data.some((s) => s.code === form.state)) {
        form.state = data[0]?.code ?? "";
      }
    } catch {
      states.value = [];
      form.state = "";
    } finally {
      statesLoading.value = false;
    }
  });

  const submit$ = $(async () => {
    const phoneCheck = validatePhone(form.dialCode, form.nationalNumber, phoneCountries.value);
    if (!phoneCheck.valid) {
      await toastError(phoneCheck.message);
      return;
    }

    if (!form.state) {
      await toastError("Please select a state or province.");
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await addCustomer({
          first_name: form.first_name.trim(),
          last_name: form.last_name.trim(),
          email: form.email.trim(),
          birth_date: form.birth_date,
          country: form.country,
          state: form.state,
          mobile: phoneCheck.fullPhone,
          dial_code: form.dialCode,
        });
        successMessage.value = data.message;
        succeeded.value = true;
        await toastSuccess(data.message);
      } catch (e) {
        if (e instanceof ApiError && e.errors) {
          const first = Object.values(e.errors)[0]?.[0];
          await toastError(first || "Could not create customer.");
        } else {
          await toastError("Could not create customer. Please try again.");
        }
      }
    });
  });

  const resetForm$ = $(() => {
    succeeded.value = false;
    successMessage.value = "";
    form.first_name = "";
    form.last_name = "";
    form.email = "";
    form.birth_date = "";
    form.country = "EG";
    form.state = "";
    form.dialCode = "+20";
    form.nationalNumber = "";
    form.mobile = "";
  });

  if (succeeded.value) {
    return (
      <section class="landing-page landing-page--add-customer">
        <div class="landing-card">
          <div class="landing-success">
            <div class="landing-success__icon" aria-hidden="true">
              ✓
            </div>
            <h1 class="landing-title">Congratulations!</h1>
            <p class="landing-success__message">{successMessage.value}</p>
            <button type="button" class="btn btn-primary" onClick$={resetForm$}>
              Add another customer
            </button>
          </div>
        </div>
      </section>
    );
  }

  const countryOptions = geoCountries.value.map((c) => ({
    value: c.code,
    label: c.name,
    searchText: c.name,
  }));

  const stateOptions = states.value.map((s) => ({
    value: s.code,
    label: s.name,
    searchText: s.name,
  }));

  const selectedCountry = geoCountries.value.find((c) => c.code === form.country);

  return (
    <section class="landing-page landing-page--add-customer">
      <div class="landing-card">
        <header class="landing-card__header">
          <h1 class="landing-title">Join us</h1>
          <p class="landing-subtitle">Create your customer account</p>
        </header>

        <form preventdefault:submit onSubmit$={submit$} class="landing-form">
          <fieldset class="landing-form__section">
            <legend>Personal information</legend>
            <div class="form-grid two-col">
              <div class="form-field">
                <label for="first_name">First name *</label>
                <input
                  id="first_name"
                  type="text"
                  value={form.first_name}
                  onInput$={(_, el) => (form.first_name = el.value)}
                  required
                  autoComplete="given-name"
                />
              </div>
              <div class="form-field">
                <label for="last_name">Last name *</label>
                <input
                  id="last_name"
                  type="text"
                  value={form.last_name}
                  onInput$={(_, el) => (form.last_name = el.value)}
                  required
                  autoComplete="family-name"
                />
              </div>
            </div>
          </fieldset>

          <fieldset class="landing-form__section">
            <legend>Contact information</legend>
            <div class="form-grid two-col">
              <div class="form-field">
                <label for="email">Email *</label>
                <input
                  id="email"
                  type="email"
                  value={form.email}
                  onInput$={(_, el) => (form.email = el.value)}
                  required
                  autoComplete="email"
                />
              </div>
              <div class="form-field">
                <label for="birth_date">Birth date *</label>
                <input
                  id="birth_date"
                  type="date"
                  value={form.birth_date}
                  onInput$={(_, el) => (form.birth_date = el.value)}
                  required
                />
              </div>
              <div class="form-field">
                <label for="country">Country *</label>
                <SearchableSelect
                  id="country"
                  options={countryOptions}
                  value={form.country}
                  displayLabel={selectedCountry?.name}
                  placeholder="Search countries…"
                  required
                  onChange$={(code) => {
                    form.country = code;
                  }}
                />
              </div>
              <div class="form-field">
                <label for="state">State / Province *</label>
                <SearchableSelect
                  id="state"
                  options={stateOptions}
                  value={form.state}
                  placeholder={statesLoading.value ? "Loading states…" : "Search states…"}
                  required
                  disabled={statesLoading.value || stateOptions.length === 0}
                  onChange$={(code) => {
                    form.state = code;
                  }}
                />
              </div>
              <div class="form-field form-field--full">
                <label for="phone">Phone number *</label>
                <PhoneInputWithDialCode
                  id="phone"
                  countries={phoneCountries.value}
                  dialCode={form.dialCode}
                  nationalNumber={form.nationalNumber}
                  required
                  onChange$={(value) => {
                    form.dialCode = value.dialCode;
                    form.nationalNumber = value.nationalNumber;
                    form.mobile = value.fullPhone;
                  }}
                />
              </div>
            </div>
          </fieldset>

          <button type="submit" class="btn btn-primary landing-form__submit" disabled={submitting.value}>
            {submitting.value ? "Creating account…" : "Create account"}
          </button>
        </form>
      </div>
    </section>
  );
});

export const head: DocumentHead = {
  title: "Join us — Add Customer",
  meta: [{ name: "robots", content: "noindex, nofollow" }],
};
