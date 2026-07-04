import { $, component$, useSignal, type QRL } from "@builder.io/qwik";
import { useI18n } from "~/lib/i18n/context";
import {
  buildFullPhone,
  phoneHint,
  sanitizeNationalNumber,
  validatePhone,
  type PhoneCountry,
} from "~/lib/phone-validation";
import { DialCodeSelect } from "./dial-code-select";

export type PhoneInputValue = {
  dialCode: string;
  nationalNumber: string;
  fullPhone: string;
};

type Props = {
  id: string;
  countries: PhoneCountry[];
  dialCode: string;
  nationalNumber: string;
  required?: boolean;
  disabled?: boolean;
  onChange$: QRL<(value: PhoneInputValue) => void>;
};

export const PhoneInputWithDialCode = component$<Props>((props) => {
  const { locale } = useI18n();
  const touched = useSignal(false);
  const error = useSignal("");

  const emit$ = $((dialCode: string, national: string) => {
    const nationalNumber = sanitizeNationalNumber(national);
    const fullPhone = buildFullPhone(dialCode, nationalNumber);
    props.onChange$({ dialCode, nationalNumber, fullPhone });
  });

  const validateNow$ = $(() => {
    const result = validatePhone(props.dialCode, props.nationalNumber, props.countries, locale);
    error.value = result.valid ? "" : result.message;
    return result.valid;
  });

  return (
    <div class="phone-input-group">
      <div class="phone-input-group__row">
        <div class="phone-input-group__dial">
          <DialCodeSelect
            id={`${props.id}-dial`}
            countries={props.countries}
            value={props.dialCode}
            required={props.required}
            disabled={props.disabled}
            onChange$={async (dialCode) => {
              await emit$(dialCode, props.nationalNumber);
              if (touched.value) {
                await validateNow$();
              }
            }}
          />
        </div>
        <div class="phone-input-group__number">
          <input
            id={props.id}
            type="tel"
            inputMode="numeric"
            autoComplete="tel-national"
            class={`phone-input-group__tel${error.value ? " phone-input-group__tel--invalid" : ""}`}
            value={props.nationalNumber}
            required={props.required}
            disabled={props.disabled}
            onInput$={async (_, el) => {
              const digits = sanitizeNationalNumber(el.value);
              if (el.value !== digits) {
                el.value = digits;
              }
              await emit$(props.dialCode, digits);
            }}
            onBlur$={async () => {
              touched.value = true;
              await validateNow$();
            }}
          />
        </div>
      </div>
      <p class="phone-input-group__hint">{phoneHint(props.dialCode, locale)}</p>
      {error.value ? <p class="phone-input-group__error" role="alert">{error.value}</p> : null}
    </div>
  );
});
