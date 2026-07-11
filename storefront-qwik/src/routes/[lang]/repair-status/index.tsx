import { $, component$, useSignal, useStore } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import {
  ApiError,
  lookupRepairStatus,
  type RepairStatusItem,
  type RepairStatusSearchType,
} from "~/lib/api";
import { toastError, toastSuccess } from "~/lib/notify";
import { usePendingState } from "~/lib/pending-context";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import { withPendingFeedback } from "~/lib/with-pending";
import { useSiteSettings } from "~/routes/[lang]/layout";

export default component$(() => {
  const settings = useSiteSettings();
  const { locale } = useI18n();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const repairs = useSignal<RepairStatusItem[]>([]);
  const searched = useSignal(false);

  const lookupEnabled = settings.value.repair?.lookup_enabled ?? true;
  const lookupByMobile = settings.value.repair?.lookup_by_mobile ?? true;

  const form = useStore({
    search_type: "job_sheet_no" as RepairStatusSearchType,
    search_number: "",
    serial_no: "",
  });

  const submit$ = $(async (event: Event) => {
    event.preventDefault();
    if (!lookupEnabled) {
      await toastError(tStatic(locale, "repair.unavailable"));
      return;
    }
    const number = form.search_number.trim();
    if (!number) {
      await toastError(tStatic(locale, "repair.searchRequired"));
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await lookupRepairStatus(
          {
            search_type: form.search_type,
            search_number: number,
            ...(form.serial_no.trim() ? { serial_no: form.serial_no.trim() } : {}),
          },
          locale,
        );
        repairs.value = data.repairs ?? [];
        searched.value = true;
        await toastSuccess(tStatic(locale, "repair.found"));
      } catch (e) {
        repairs.value = [];
        searched.value = true;
        const message =
          e instanceof ApiError
            ? e.message || tStatic(locale, "repair.notFound")
            : tStatic(locale, "repair.notFound");
        await toastError(message);
      }
    });
  });

  return (
    <article class="content-page repair-status-page">
      <nav class="content-breadcrumb" aria-label={tStatic(locale, "a11y.breadcrumb")}>
        <Link href={localePath(locale, "/")}>{tStatic(locale, "nav.home")}</Link>
        <span aria-hidden="true">›</span>
        <span>{tStatic(locale, "nav.trackRepairs")}</span>
      </nav>

      <h1 class="content-title">{tStatic(locale, "repair.title")}</h1>
      <p class="content-lead">{tStatic(locale, "repair.intro")}</p>

      {!lookupEnabled ? (
        <p class="footer-muted">{tStatic(locale, "repair.unavailable")}</p>
      ) : (
        <form class="repair-status-form" preventdefault:submit onSubmit$={submit$}>
          {/* Search-by selector: job sheet, invoice, or mobile */}
          <div>
            <label for="repair_search_type">{tStatic(locale, "repair.searchBy")}</label>
            <select
              id="repair_search_type"
              value={form.search_type}
              onChange$={(_, el) => {
                form.search_type = el.value as RepairStatusSearchType;
              }}
            >
              <option value="job_sheet_no">{tStatic(locale, "repair.jobSheetNo")}</option>
              <option value="invoice_no">{tStatic(locale, "repair.invoiceNo")}</option>
              {lookupByMobile ? (
                <option value="mobile_num">{tStatic(locale, "repair.mobile")}</option>
              ) : null}
            </select>
          </div>

          <div>
            <label for="repair_search_number">{tStatic(locale, "repair.searchNumber")}</label>
            <input
              id="repair_search_number"
              type="text"
              required
              autocomplete="off"
              value={form.search_number}
              placeholder={tStatic(locale, "repair.searchPlaceholder")}
              onInput$={(_, el) => {
                form.search_number = el.value;
              }}
            />
          </div>

          <div>
            <label for="repair_serial_no">{tStatic(locale, "repair.serialNo")}</label>
            <input
              id="repair_serial_no"
              type="text"
              autocomplete="off"
              value={form.serial_no}
              placeholder={tStatic(locale, "repair.serialOptional")}
              onInput$={(_, el) => {
                form.serial_no = el.value;
              }}
            />
          </div>

          <button type="submit" class="btn btn-primary" disabled={submitting.value}>
            {submitting.value ? tStatic(locale, "repair.searching") : tStatic(locale, "repair.search")}
          </button>
        </form>
      )}

      {searched.value && repairs.value.length === 0 ? (
        <p class="footer-muted repair-status-empty">{tStatic(locale, "repair.notFound")}</p>
      ) : null}

      {repairs.value.length > 0 ? (
        <div class="repair-status-results">
          {repairs.value.map((repair) => (
            <section key={repair.job_sheet_no} class="repair-status-card">
              <header class="repair-status-card__head">
                <h2>{repair.job_sheet_no}</h2>
                {repair.status ? (
                  <span
                    class="repair-status-badge"
                    style={
                      repair.status_color
                        ? { backgroundColor: repair.status_color }
                        : undefined
                    }
                  >
                    {repair.status}
                  </span>
                ) : null}
              </header>

              <dl class="repair-status-meta">
                {repair.brand ? (
                  <>
                    <dt>{tStatic(locale, "repair.brand")}</dt>
                    <dd>{repair.brand}</dd>
                  </>
                ) : null}
                {repair.device ? (
                  <>
                    <dt>{tStatic(locale, "repair.device")}</dt>
                    <dd>{repair.device}</dd>
                  </>
                ) : null}
                {repair.model ? (
                  <>
                    <dt>{tStatic(locale, "repair.model")}</dt>
                    <dd>{repair.model}</dd>
                  </>
                ) : null}
                {repair.serial_no ? (
                  <>
                    <dt>{tStatic(locale, "repair.serialNo")}</dt>
                    <dd>{repair.serial_no}</dd>
                  </>
                ) : null}
                {repair.due_date_label ? (
                  <>
                    <dt>{tStatic(locale, "repair.dueDate")}</dt>
                    <dd>{repair.due_date_label}</dd>
                  </>
                ) : null}
              </dl>

              <h3 class="repair-status-activities-title">{tStatic(locale, "repair.activities")}</h3>
              {repair.activities.length === 0 ? (
                <p class="footer-muted">{tStatic(locale, "repair.noActivities")}</p>
              ) : (
                <ul class="repair-status-activities">
                  {repair.activities.map((activity, index) => (
                    <li key={`${repair.job_sheet_no}-${index}`}>
                      <div class="repair-status-activity__when">
                        {activity.date_label || activity.date}
                      </div>
                      <div class="repair-status-activity__action">{activity.action}</div>
                      <div class="repair-status-activity__by">
                        {tStatic(locale, "repair.by")}: {activity.by}
                      </div>
                      {activity.note ? (
                        <div class="repair-status-activity__note">{activity.note}</div>
                      ) : null}
                    </li>
                  ))}
                </ul>
              )}
            </section>
          ))}
        </div>
      ) : null}
    </article>
  );
});

export const head: DocumentHead = ({ resolveValue, url, params }) => {
  const settings = resolveValue(useSiteSettings);
  const lang = isSupportedLocale(params.lang) ? params.lang : "en";
  const title = tStatic(lang, "repair.seoTitle").replace("{businessName}", settings.business_name);
  const description = tStatic(lang, "repair.seoDescription").replace(
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
      links: publicSeoLinks(url.origin, "/repair-status", lang),
    },
    settings,
  );
};
