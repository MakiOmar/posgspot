import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import {
  Link,
  routeLoader$,
  useLocation,
  type DocumentHead,
} from "@builder.io/qwik-city";
import { JsonLd } from "~/components/seo/json-ld";
import { LockIcon } from "~/components/icons";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { SearchableSelect } from "~/components/forms/searchable-select";
import {
  ApiError,
  fetchSellToUsMeta,
  submitSellToUsRequest,
  verifySellToUsInvoice,
} from "~/lib/api";
import { isAuthenticated } from "~/lib/auth-actions";
import { useAuth } from "~/lib/auth-context";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { toastError, toastSuccess } from "~/lib/notify";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { SellToUsMeta, SellToUsType } from "~/lib/types";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

export const useSellToUsPage = routeLoader$(async ({ params, redirect, resolveValue }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const settings = await resolveValue(useSiteSettings);
  if (!settings.sell_to_us?.enabled) {
    throw redirect(302, localePath(locale, "/"));
  }

  try {
    const { data } = await fetchSellToUsMeta(locale);
    return { meta: data, unavailable: false as const };
  } catch (e) {
    const status = e instanceof ApiError ? e.status : 0;
    if (status === 404) {
      throw redirect(302, localePath(locale, "/"));
    }
    return {
      meta: {
        enabled: true,
        types: [],
        cities: [],
        platforms: [
          { id: "ps5", label: "PS5" },
          { id: "ps4", label: "PS4" },
        ],
        device_models: [],
        storage_options: [],
        conditions: [],
        purchased_from_us: {
          invoice_required_when_yes: true,
          verify_required_when_yes: true,
        },
        max_photos: 6,
        max_photo_kb: 4096,
      } satisfies SellToUsMeta,
      unavailable: true as const,
    };
  }
});

/** Sell to us / trade-in quote request (logged-in customers). */
export default component$(() => {
  const page = useSellToUsPage();
  const { locale } = useI18n();
  const auth = useAuth();
  const loc = useLocation();
  const meta = page.value.meta;

  const type = useSignal<SellToUsType | "">("");
  const purchasedFromUs = useSignal(false);
  const invoiceVerified = useSignal(false);
  const verifiedTxnId = useSignal<number | null>(null);
  const verifying = useSignal(false);
  const submitting = useSignal(false);
  const photoFiles = useSignal<File[]>([]);

  const form = useStore({
    name: "",
    email: "",
    phone: "",
    city: "",
    notes: "",
    invoice_no: "",
    game_title: "",
    platform: "",
    condition: "",
    model: "",
    storage: "",
    account_note: "",
  });

  // Prefill from account once auth is ready.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(({ track }) => {
    track(() => auth.ready);
    track(() => auth.contact);
    if (!auth.ready || !auth.contact) return;
    if (!form.name) form.name = auth.contact.name || "";
    if (!form.email) form.email = auth.contact.email || "";
    if (!form.phone) form.phone = auth.contact.mobile || "";
    if (!form.city && auth.contact.city) form.city = auth.contact.city;
  });

  const selectType$ = $((id: SellToUsType) => {
    type.value = id;
  });

  const onPurchasedChange$ = $((yes: boolean) => {
    purchasedFromUs.value = yes;
    invoiceVerified.value = false;
    verifiedTxnId.value = null;
  });

  const onInvoiceInput$ = $((value: string) => {
    form.invoice_no = value;
    invoiceVerified.value = false;
    verifiedTxnId.value = null;
  });

  const verify$ = $(async () => {
    if (!auth.token) return;
    verifying.value = true;
    try {
      const { data } = await verifySellToUsInvoice(auth.token, form.invoice_no.trim());
      if (data.valid && data.order) {
        invoiceVerified.value = true;
        verifiedTxnId.value = data.order.id;
        await toastSuccess(tStatic(locale, "sellToUs.verified"));
      } else {
        await toastError(tStatic(locale, "sellToUs.verifyFailed"));
      }
    } catch (e) {
      await toastError(
        e instanceof ApiError ? e.message : tStatic(locale, "sellToUs.verifyFailed"),
      );
    } finally {
      verifying.value = false;
    }
  });

  const onPhotos$ = $((files: FileList | null) => {
    if (!files) {
      photoFiles.value = [];
      return;
    }
    photoFiles.value = Array.from(files).slice(0, meta.max_photos);
  });

  const submit$ = $(async () => {
    if (!auth.token || !type.value) return;
    if (purchasedFromUs.value && !invoiceVerified.value) {
      await toastError(tStatic(locale, "sellToUs.verifyRequired"));
      return;
    }

    submitting.value = true;
    try {
      const body = new FormData();
      body.append("type", type.value);
      body.append("name", form.name.trim());
      body.append("email", form.email.trim());
      body.append("phone", form.phone.trim());
      body.append("city", form.city.trim());
      body.append("notes", form.notes.trim());
      body.append("purchased_from_us", purchasedFromUs.value ? "1" : "0");
      if (purchasedFromUs.value) {
        body.append("invoice_no", form.invoice_no.trim());
        if (verifiedTxnId.value) {
          body.append("transaction_id", String(verifiedTxnId.value));
        }
      }

      const details: Record<string, string | number | null> = {};
      if (type.value === "account") {
        details.account_note = form.account_note.trim();
      } else if (type.value === "disc") {
        details.game_title = form.game_title.trim();
        details.platform = form.platform || null;
        details.condition = form.condition || null;
      } else if (type.value === "device") {
        details.model = form.model.trim();
        details.storage = form.storage.trim();
        details.condition = form.condition || null;
      }
      body.append("details", JSON.stringify(details));

      for (const file of photoFiles.value) {
        body.append("photos[]", file);
      }

      await submitSellToUsRequest(auth.token, body);
      await toastSuccess(tStatic(locale, "sellToUs.success"));
      form.notes = "";
      form.invoice_no = "";
      form.game_title = "";
      form.platform = "";
      form.condition = "";
      form.model = "";
      form.storage = "";
      form.account_note = "";
      purchasedFromUs.value = false;
      invoiceVerified.value = false;
      verifiedTxnId.value = null;
      photoFiles.value = [];
      type.value = "";
    } catch (e) {
      await toastError(
        e instanceof ApiError ? e.message : tStatic(locale, "sellToUs.submitFailed"),
      );
    } finally {
      submitting.value = false;
    }
  });

  const signedIn = isAuthenticated(auth);
  const loginNext = encodeURIComponent(loc.url.pathname);
  const howLd = {
    "@context": "https://schema.org",
    "@type": "HowTo",
    name: tStatic(locale, "sellToUs.title"),
    description: tStatic(locale, "sellToUs.lead"),
    step: [
      {
        "@type": "HowToStep",
        name: tStatic(locale, "sellToUs.step1Title"),
        text: tStatic(locale, "sellToUs.step1Text"),
      },
      {
        "@type": "HowToStep",
        name: tStatic(locale, "sellToUs.step2Title"),
        text: tStatic(locale, "sellToUs.step2Text"),
      },
      {
        "@type": "HowToStep",
        name: tStatic(locale, "sellToUs.step3Title"),
        text: tStatic(locale, "sellToUs.step3Text"),
      },
    ],
  };

  // Full page gate until auth is known and the customer is signed in.
  if (!auth.ready || !signedIn) {
    return (
      <div class="sell-to-us-layout-page">
        <PageTitleBar
          title={tStatic(locale, "sellToUs.title")}
          crumbs={[{ label: tStatic(locale, "nav.sellToUs") }]}
        />
        <article class="content-page sell-to-us-page sell-to-us-page--gate">
        <div class="sell-to-us-gate">
          {!auth.ready ? (
            <p class="footer-muted sell-to-us-gate__loading" aria-live="polite">
              …
            </p>
          ) : (
            <div class="sell-to-us-gate__card">
              <p class="sell-to-us-gate__badge">{tStatic(locale, "sellToUs.gateBadge")}</p>
              <div class="sell-to-us-gate__icon" aria-hidden="true">
                <LockIcon size={48} />
              </div>
              <h2 class="sell-to-us-gate__title">{tStatic(locale, "sellToUs.gateTitle")}</h2>
              <p class="sell-to-us-gate__lead">{tStatic(locale, "sellToUs.gateLead")}</p>
              <Link
                class="btn btn-primary sell-to-us-gate__cta"
                href={localePath(locale, `/login?next=${loginNext}`)}
              >
                {tStatic(locale, "sellToUs.loginCta")}
              </Link>
              <p class="sell-to-us-gate__register">
                {tStatic(locale, "sellToUs.gateRegisterBefore")}{" "}
                <Link href={localePath(locale, `/register?next=${loginNext}`)}>
                  {tStatic(locale, "sellToUs.gateRegisterLink")}
                </Link>{" "}
                {tStatic(locale, "sellToUs.gateRegisterAfter")}
              </p>
            </div>
          )}
        </div>
        </article>
      </div>
    );
  }

  return (
    <div class="sell-to-us-layout-page">
      <PageTitleBar
        title={tStatic(locale, "sellToUs.title")}
        crumbs={[{ label: tStatic(locale, "nav.sellToUs") }]}
      />
      <article class="content-page sell-to-us-page">
      <JsonLd data={howLd} />

      <p class="sell-to-us-badge">{tStatic(locale, "sellToUs.badge")}</p>
      <p class="content-prose sell-to-us-lead">{tStatic(locale, "sellToUs.lead")}</p>

      <h2 class="sell-to-us-section-title">{tStatic(locale, "sellToUs.howTitle")}</h2>
      <ol class="sell-to-us-steps">
        <li>
          <strong>{tStatic(locale, "sellToUs.step1Title")}</strong>
          <span>{tStatic(locale, "sellToUs.step1Text")}</span>
        </li>
        <li>
          <strong>{tStatic(locale, "sellToUs.step2Title")}</strong>
          <span>{tStatic(locale, "sellToUs.step2Text")}</span>
        </li>
        <li>
          <strong>{tStatic(locale, "sellToUs.step3Title")}</strong>
          <span>{tStatic(locale, "sellToUs.step3Text")}</span>
        </li>
      </ol>

      {page.value.unavailable ? (
        <p class="footer-muted">{tStatic(locale, "sellToUs.loadFailed")}</p>
      ) : (
        <>
          <h2 class="sell-to-us-section-title">{tStatic(locale, "sellToUs.chooseType")}</h2>
          <div class="sell-to-us-types" role="group">
            {(
              [
                ["account", "sellToUs.typeAccount", "sellToUs.typeAccountHint"],
                ["disc", "sellToUs.typeDisc", "sellToUs.typeDiscHint"],
                ["device", "sellToUs.typeDevice", "sellToUs.typeDeviceHint"],
              ] as const
            ).map(([id, labelKey, hintKey]) => (
              <button
                key={id}
                type="button"
                class={`sell-to-us-type${type.value === id ? " is-active" : ""}`}
                onClick$={() => selectType$(id)}
              >
                <strong>{tStatic(locale, labelKey)}</strong>
                <span>{tStatic(locale, hintKey)}</span>
              </button>
            ))}
          </div>

          {type.value ? (
            <form
              class="sell-to-us-form"
              preventdefault:submit
              onSubmit$={submit$}
            >
              <h2 class="sell-to-us-section-title">{tStatic(locale, "sellToUs.formTitle")}</h2>

              <div class="sell-to-us-grid">
                <label>
                  <span>{tStatic(locale, "sellToUs.name")}</span>
                  <input
                    required
                    type="text"
                    value={form.name}
                    onInput$={(e) => {
                      form.name = (e.target as HTMLInputElement).value;
                    }}
                  />
                </label>
                <label>
                  <span>{tStatic(locale, "sellToUs.email")}</span>
                  <input
                    type="email"
                    value={form.email}
                    onInput$={(e) => {
                      form.email = (e.target as HTMLInputElement).value;
                    }}
                  />
                </label>
                <label>
                  <span>{tStatic(locale, "sellToUs.phone")}</span>
                  <input
                    type="tel"
                    value={form.phone}
                    onInput$={(e) => {
                      form.phone = (e.target as HTMLInputElement).value;
                    }}
                  />
                </label>
                <label>
                  <span>{tStatic(locale, "sellToUs.city")}</span>
                  <SearchableSelect
                    id="sell-city"
                    value={form.city}
                    placeholder={tStatic(locale, "sellToUs.selectCity")}
                    options={meta.cities.map((c) => ({
                      value: c.label,
                      label: c.label,
                    }))}
                    onChange$={(value) => {
                      form.city = value;
                    }}
                  />
                </label>
              </div>

              <fieldset class="sell-to-us-fieldset">
                <legend>{tStatic(locale, "sellToUs.purchasedFromUs")}</legend>
                <div class="sell-to-us-yesno">
                  <button
                    type="button"
                    class={!purchasedFromUs.value ? "is-active" : ""}
                    onClick$={() => onPurchasedChange$(false)}
                  >
                    {tStatic(locale, "sellToUs.no")}
                  </button>
                  <button
                    type="button"
                    class={purchasedFromUs.value ? "is-active" : ""}
                    onClick$={() => onPurchasedChange$(true)}
                  >
                    {tStatic(locale, "sellToUs.yes")}
                  </button>
                </div>
                {purchasedFromUs.value ? (
                  <div class="sell-to-us-verify">
                    <label>
                      <span>{tStatic(locale, "sellToUs.invoiceNo")}</span>
                      <input
                        required
                        type="text"
                        value={form.invoice_no}
                        onInput$={(e) =>
                          onInvoiceInput$((e.target as HTMLInputElement).value)
                        }
                      />
                    </label>
                    <button
                      type="button"
                      class="btn btn-secondary"
                      disabled={verifying.value || !form.invoice_no.trim()}
                      onClick$={verify$}
                    >
                      {tStatic(locale, "sellToUs.verify")}
                    </button>
                    {invoiceVerified.value ? (
                      <p class="sell-to-us-ok">{tStatic(locale, "sellToUs.verified")}</p>
                    ) : null}
                  </div>
                ) : null}
              </fieldset>

              {type.value === "account" ? (
                <label class="sell-to-us-block">
                  <span>{tStatic(locale, "sellToUs.accountNote")}</span>
                  <textarea
                    rows={3}
                    placeholder={tStatic(locale, "sellToUs.accountNotePlaceholder")}
                    value={form.account_note}
                    onInput$={(e) => {
                      form.account_note = (e.target as HTMLTextAreaElement).value;
                    }}
                  />
                </label>
              ) : null}

              {type.value === "disc" ? (
                <div class="sell-to-us-grid">
                  <label class="sell-to-us-span2">
                    <span>{tStatic(locale, "sellToUs.gameTitle")}</span>
                    <input
                      required
                      type="text"
                      value={form.game_title}
                      onInput$={(e) => {
                        form.game_title = (e.target as HTMLInputElement).value;
                      }}
                    />
                  </label>
                  <label>
                    <span>{tStatic(locale, "sellToUs.platform")}</span>
                    <SearchableSelect
                      id="sell-disc-platform"
                      value={form.platform}
                      placeholder="—"
                      options={meta.platforms.map((p) => ({
                        value: p.id,
                        label: p.label,
                      }))}
                      onChange$={(value) => {
                        form.platform = value;
                      }}
                    />
                  </label>
                  <label>
                    <span>{tStatic(locale, "sellToUs.condition")}</span>
                    <SearchableSelect
                      id="sell-disc-condition"
                      value={form.condition}
                      placeholder="—"
                      options={meta.conditions.map((c) => ({
                        value: c.id,
                        label: c.label,
                      }))}
                      onChange$={(value) => {
                        form.condition = value;
                      }}
                    />
                  </label>
                </div>
              ) : null}

              {type.value === "device" ? (
                <>
                  <div class="sell-to-us-grid">
                    <label>
                      <span>{tStatic(locale, "sellToUs.model")}</span>
                      <SearchableSelect
                        id="sell-device-model"
                        value={form.model}
                        placeholder="—"
                        required
                        options={meta.device_models.map((m) => ({
                          value: m.id,
                          label: m.label,
                        }))}
                        onChange$={(value) => {
                          form.model = value;
                        }}
                      />
                    </label>
                    <label>
                      <span>{tStatic(locale, "sellToUs.storage")}</span>
                      <SearchableSelect
                        id="sell-device-storage"
                        value={form.storage}
                        placeholder="—"
                        options={meta.storage_options.map((s) => ({
                          value: s.id,
                          label: s.label,
                        }))}
                        onChange$={(value) => {
                          form.storage = value;
                        }}
                      />
                    </label>
                    <label>
                      <span>{tStatic(locale, "sellToUs.condition")}</span>
                      <SearchableSelect
                        id="sell-device-condition"
                        value={form.condition}
                        placeholder="—"
                        required
                        options={meta.conditions.map((c) => ({
                          value: c.id,
                          label: c.label,
                        }))}
                        onChange$={(value) => {
                          form.condition = value;
                        }}
                      />
                    </label>
                  </div>
                  <label class="sell-to-us-block">
                    <span>{tStatic(locale, "sellToUs.photos")}</span>
                    <span class="footer-muted">
                      {tStatic(locale, "sellToUs.photosHint", {
                        max: String(meta.max_photos),
                        kb: String(meta.max_photo_kb),
                      })}
                    </span>
                    <input
                      type="file"
                      accept="image/*"
                      multiple
                      onChange$={(e) =>
                        onPhotos$((e.target as HTMLInputElement).files)
                      }
                    />
                  </label>
                </>
              ) : null}

              <label class="sell-to-us-block">
                <span>{tStatic(locale, "sellToUs.notes")}</span>
                <textarea
                  rows={3}
                  placeholder={tStatic(locale, "sellToUs.notesPlaceholder")}
                  value={form.notes}
                  onInput$={(e) => {
                    form.notes = (e.target as HTMLTextAreaElement).value;
                  }}
                />
              </label>

              <button
                type="submit"
                class="btn btn-primary"
                disabled={submitting.value}
              >
                {submitting.value
                  ? tStatic(locale, "sellToUs.submitting")
                  : tStatic(locale, "sellToUs.submit")}
              </button>
            </form>
          ) : null}
        </>
      )}
      </article>
    </div>
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const title = `${tStatic(lang, "sellToUs.title")} — ${settings.business_name}`;
  const description = tStatic(lang, "sellToUs.lead");

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
      links: publicSeoLinks(url.origin, "/sell-to-us", lang),
    },
    settings,
  );
};
