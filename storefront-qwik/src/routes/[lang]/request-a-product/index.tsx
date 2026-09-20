import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, routeLoader$, type DocumentHead } from "@builder.io/qwik-city";
import { PhoneInputWithDialCode } from "~/components/forms/phone-input-with-dial-code";
import { TurnstileWidget } from "~/components/forms/turnstile-widget";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import {
  ApiError,
  fetchPhoneCountries,
  fetchRequestProductMeta,
  submitRequestProduct,
} from "~/lib/api";
import { isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { validatePhone } from "~/lib/phone-validation";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { RequestProductMeta } from "~/lib/types";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/[lang]/layout";

export const useRequestProductPage = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.request_product?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  let meta: RequestProductMeta = { enabled: true, platforms: [] };
  try {
    const { data } = await fetchRequestProductMeta(locale);
    meta = data;
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) {
      throw redirect(302, localePath(locale, "/"));
    }
  }

  let phoneCountries: Awaited<ReturnType<typeof fetchPhoneCountries>>["data"] = [];
  try {
    const { data } = await fetchPhoneCountries();
    phoneCountries = data;
  } catch {
    phoneCountries = [];
  }

  return { meta, phoneCountries };
});

/** Free-text product sourcing request (Sell-to-us pattern, not CRM). */
export default component$(() => {
  const page = useRequestProductPage();
  const settings = useSiteSettings();
  const auth = useAuth();
  const { locale } = useI18n();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const turnstileToken = useSignal("");
  const turnstileResetKey = useSignal(0);
  const turnstile = settings.value.turnstile;
  const turnstileEnabled = Boolean(turnstile?.enabled && turnstile.site_key);
  const signedIn = isAuthenticated(auth);

  const form = useStore({
    name: "",
    email: "",
    dialCode: "+20",
    nationalNumber: "",
    product_name: "",
    platform: "",
    notes: "",
  });

  // Prefill from account once auth is ready.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.contact);
    if (!auth.ready || !auth.contact) {
      return;
    }
    if (!form.name) {
      form.name = auth.contact.name || [auth.contact.first_name, auth.contact.last_name].filter(Boolean).join(" ");
    }
    if (!form.email && auth.contact.email) {
      form.email = auth.contact.email;
    }
    if (!form.nationalNumber && auth.contact.mobile) {
      form.nationalNumber = auth.contact.mobile.replace(/^\+?\d{1,3}/, "").replace(/\D/g, "");
    }
  });

  const submit$ = $(async () => {
    if (!form.product_name.trim() || !form.name.trim() || !form.email.trim()) {
      await toastError(tStatic(locale, "requestProduct.failed"));
      return;
    }
    if (turnstileEnabled && !turnstileToken.value) {
      await toastError(tStatic(locale, "turnstile.required"));
      return;
    }

    let phonePayload: { phone?: string; dial_code?: string } = {};
    if (form.nationalNumber.trim()) {
      const phoneCheck = validatePhone(
        form.dialCode,
        form.nationalNumber,
        page.value.phoneCountries,
        locale,
      );
      if (!phoneCheck.valid) {
        await toastError(phoneCheck.message || tStatic(locale, "requestProduct.failed"));
        return;
      }
      phonePayload = { phone: phoneCheck.fullPhone, dial_code: form.dialCode };
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        await submitRequestProduct(
          {
            name: form.name.trim(),
            email: form.email.trim(),
            ...phonePayload,
            product_name: form.product_name.trim(),
            ...(form.platform ? { platform: form.platform } : {}),
            ...(form.notes.trim() ? { notes: form.notes.trim() } : {}),
            ...(turnstileEnabled ? { turnstile_token: turnstileToken.value } : {}),
          },
          auth.token,
        );
        form.product_name = "";
        form.platform = "";
        form.notes = "";
        turnstileResetKey.value += 1;
        await toastSuccess(tStatic(locale, "requestProduct.success"));
      } catch (e) {
        turnstileResetKey.value += 1;
        const message =
          e instanceof ApiError
            ? e.message || tStatic(locale, "requestProduct.failed")
            : tStatic(locale, "requestProduct.failed");
        await toastError(message);
      }
    });
  });

  return (
    <div class="request-product-layout-page">
      <PageTitleBar
        title={tStatic(locale, "requestProduct.title")}
        crumbs={[{ label: tStatic(locale, "nav.requestProduct") }]}
      />
      <article class="content-page request-product-page">
      <p class="coming-soon-badge">{tStatic(locale, "requestProduct.badge")}</p>
      <p class="content-lead">{tStatic(locale, "requestProduct.lead")}</p>

      {!signedIn && auth.ready ? (
        <p class="footer-muted">
          {tStatic(locale, "requestProduct.loginOptional")}{" "}
          <Link href={localePath(locale, "/login")}>{tStatic(locale, "header.signIn")}</Link>
        </p>
      ) : null}

      <form class="repair-status-form request-product-form" preventdefault:submit onSubmit$={submit$}>
        <div>
          <label for="rp_product_name">{tStatic(locale, "requestProduct.productName")}</label>
          <input
            id="rp_product_name"
            type="text"
            required
            value={form.product_name}
            onInput$={(_, el) => {
              form.product_name = el.value;
            }}
          />
        </div>

        <div>
          <label for="rp_platform">{tStatic(locale, "requestProduct.platform")}</label>
          <select
            id="rp_platform"
            value={form.platform}
            onChange$={(_, el) => {
              form.platform = el.value;
            }}
          >
            <option value="">—</option>
            {page.value.meta.platforms.map((p) => (
              <option key={p.id} value={p.id}>
                {p.label}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label for="rp_notes">{tStatic(locale, "requestProduct.notes")}</label>
          <textarea
            id="rp_notes"
            rows={4}
            placeholder={tStatic(locale, "requestProduct.notesPlaceholder")}
            value={form.notes}
            onInput$={(_, el) => {
              form.notes = el.value;
            }}
          />
        </div>

        <div>
          <label for="rp_name">{tStatic(locale, "requestProduct.name")}</label>
          <input
            id="rp_name"
            type="text"
            required
            autocomplete="name"
            value={form.name}
            onInput$={(_, el) => {
              form.name = el.value;
            }}
          />
        </div>

        <div>
          <label for="rp_email">{tStatic(locale, "requestProduct.email")}</label>
          <input
            id="rp_email"
            type="email"
            required
            autocomplete="email"
            value={form.email}
            onInput$={(_, el) => {
              form.email = el.value;
            }}
          />
        </div>

        <div>
          <label for="rp_phone">{tStatic(locale, "requestProduct.phone")}</label>
          <PhoneInputWithDialCode
            id="rp_phone"
            countries={page.value.phoneCountries}
            dialCode={form.dialCode}
            nationalNumber={form.nationalNumber}
            onChange$={(value) => {
              form.dialCode = value.dialCode;
              form.nationalNumber = value.nationalNumber;
            }}
          />
        </div>

        {turnstileEnabled && turnstile.site_key ? (
          <TurnstileWidget
            siteKey={turnstile.site_key}
            token={turnstileToken}
            resetKey={turnstileResetKey.value}
          />
        ) : null}

        <button type="submit" class="btn btn-primary" disabled={submitting.value}>
          {submitting.value
            ? tStatic(locale, "requestProduct.submitting")
            : tStatic(locale, "requestProduct.submit")}
        </button>
      </form>
      </article>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = `${tStatic(lang, "requestProduct.title")} — ${settings.business_name}`;
  const description = tStatic(lang, "requestProduct.lead");

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
      links: publicSeoLinks(url.origin, "/request-a-product", lang),
    },
    settings,
  );
};
