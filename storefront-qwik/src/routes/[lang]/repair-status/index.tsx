import { $, component$, useSignal, useStore, useVisibleTask$ } from "@builder.io/qwik";
import { Link, type DocumentHead } from "@builder.io/qwik-city";
import { TurnstileWidget } from "~/components/forms/turnstile-widget";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import {
  ApiError,
  fetchAccountRepairs,
  lookupRepairStatus,
  type RepairStatusItem,
  type RepairStatusSearchType,
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

const RepairCards = component$<{ repairs: RepairStatusItem[] }>(({ repairs }) => {
  const { locale } = useI18n();

  return (
    <div class="repair-status-results">
      {repairs.map((repair) => (
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
  );
});

export default component$(() => {
  const settings = useSiteSettings();
  const auth = useAuth();
  const { locale } = useI18n();
  const pending = usePendingState();
  const submitting = useSignal(false);
  const repairs = useSignal<RepairStatusItem[]>([]);
  const searched = useSignal(false);
  const myRepairs = useSignal<RepairStatusItem[]>([]);
  const myRepairsLoaded = useSignal(false);
  const myRepairsLoading = useSignal(false);

  const lookupEnabled = settings.value.repair?.lookup_enabled ?? true;
  const lookupByMobile = settings.value.repair?.lookup_by_mobile ?? true;
  const turnstile = settings.value.turnstile;
  const turnstileEnabled = Boolean(turnstile?.enabled && turnstile.site_key);
  const turnstileToken = useSignal("");
  const turnstileResetKey = useSignal(0);
  const signedIn = isAuthenticated(auth);
  const authPending = !auth.ready;
  const authPendingAttr = authPending ? "1" : undefined;

  const form = useStore({
    search_type: (lookupByMobile ? "mobile_num" : "job_sheet_no") as RepairStatusSearchType,
    search_number: "",
    serial_no: "",
  });

  const searchNumberPlaceholder =
    form.search_type === "job_sheet_no"
      ? tStatic(locale, "repair.jobSheetPlaceholder")
      : form.search_type === "invoice_no"
        ? tStatic(locale, "repair.invoicePlaceholder")
        : tStatic(locale, "repair.mobilePlaceholder");

  // Load signed-in customer's repairs once auth is ready.
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(async ({ track }) => {
    track(() => auth.ready);
    track(() => auth.token);
    if (!auth.ready) {
      return;
    }
    if (!auth.token) {
      myRepairs.value = [];
      myRepairsLoaded.value = true;
      myRepairsLoading.value = false;
      return;
    }
    if (!lookupEnabled) {
      myRepairsLoaded.value = true;
      return;
    }

    myRepairsLoading.value = true;
    try {
      const { data } = await fetchAccountRepairs(auth.token, locale);
      myRepairs.value = data.repairs ?? [];
    } catch {
      myRepairs.value = [];
    } finally {
      myRepairsLoading.value = false;
      myRepairsLoaded.value = true;
    }
  });

  const submit$ = $(async () => {
    if (!lookupEnabled) {
      await toastError(tStatic(locale, "repair.unavailable"));
      return;
    }
    if (!form.search_type) {
      await toastError(tStatic(locale, "repair.chooseSearchBy"));
      return;
    }
    const number = form.search_number.trim();
    if (!number) {
      await toastError(tStatic(locale, "repair.searchRequired"));
      return;
    }
    if (turnstileEnabled && !turnstileToken.value) {
      await toastError(tStatic(locale, "turnstile.required"));
      return;
    }

    await withPendingFeedback(pending, submitting, async () => {
      try {
        const { data } = await lookupRepairStatus(
          {
            search_type: form.search_type as RepairStatusSearchType,
            search_number: number,
            ...(form.serial_no.trim() ? { serial_no: form.serial_no.trim() } : {}),
            ...(turnstileEnabled ? { turnstile_token: turnstileToken.value } : {}),
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
      } finally {
        // Turnstile tokens are single-use; get a fresh one for the next search.
        turnstileResetKey.value += 1;
      }
    });
  });

  return (
    <div class="repair-status-layout-page">
      <PageTitleBar
        title={tStatic(locale, "repair.title")}
        crumbs={[{ label: tStatic(locale, "nav.trackRepairs") }]}
      />
      <article class="content-page repair-status-page">
      {/* Guest-only intro; signed-in customers see their list instead of lookup copy. */}
      {!signedIn ? (
        <p class="content-lead" data-auth-view="guest" data-auth-pending={authPendingAttr}>
          {tStatic(locale, "repair.intro")}
        </p>
      ) : null}

      {/* Signed-in: auto-list only. Before auth hydrates, CSS shows this only with a saved session. */}
      {lookupEnabled && (signedIn || authPending) ? (
        <section
          class="repair-status-mine"
          aria-live="polite"
          data-auth-view="mine"
          data-auth-pending={authPendingAttr}
        >
          <h2 class="repair-status-mine__title">{tStatic(locale, "repair.myRepairs")}</h2>
          {authPending || myRepairsLoading.value ? (
            <p class="footer-muted">{tStatic(locale, "repair.loadingMine")}</p>
          ) : myRepairsLoaded.value && myRepairs.value.length === 0 ? (
            <p class="footer-muted">{tStatic(locale, "repair.myRepairsEmpty")}</p>
          ) : myRepairs.value.length > 0 ? (
            <RepairCards repairs={myRepairs.value} />
          ) : null}
        </section>
      ) : null}

      {/* Guests: job sheet / invoice / mobile lookup, server-rendered so it is usable before auth hydrates. */}
      {!signedIn ? (
        <div class="repair-status-layout" data-auth-view="guest" data-auth-pending={authPendingAttr}>
        <div class="repair-status-layout__form">
          {!lookupEnabled ? (
            <p class="footer-muted">{tStatic(locale, "repair.unavailable")}</p>
          ) : (
            <>
            <p class="footer-muted">
              {tStatic(locale, "repair.myRepairsSignIn")}{" "}
              <Link href={localePath(locale, "/login")}>{tStatic(locale, "header.signIn")}</Link>
            </p>
            <form class="repair-status-form" preventdefault:submit onSubmit$={submit$}>
              {/* Default is mobile number when that lookup is enabled. */}
              <div>
                <label for="repair_search_type">{tStatic(locale, "repair.searchBy")}</label>
                <select
                  id="repair_search_type"
                  required
                  value={form.search_type}
                  onChange$={(_, el) => {
                    form.search_type = el.value as RepairStatusSearchType;
                  }}
                >
                  {/* Default option first: SSR HTML ignores `value` on <select>, so the first option shows until hydration. */}
                  {lookupByMobile ? (
                    <option value="mobile_num">{tStatic(locale, "repair.mobile")}</option>
                  ) : null}
                  <option value="job_sheet_no">{tStatic(locale, "repair.jobSheetNo")}</option>
                  <option value="invoice_no">{tStatic(locale, "repair.invoiceNo")}</option>
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
                  placeholder={searchNumberPlaceholder}
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

              {/* Security check required by POST /repair/status when Turnstile is configured. */}
              {turnstileEnabled && turnstile.site_key ? (
                <TurnstileWidget
                  siteKey={turnstile.site_key}
                  token={turnstileToken}
                  resetKey={turnstileResetKey.value}
                  activation="visible"
                />
              ) : null}

              <button type="submit" class="btn btn-primary" disabled={submitting.value}>
                {submitting.value ? tStatic(locale, "repair.searching") : tStatic(locale, "repair.search")}
              </button>
            </form>
            </>
          )}
        </div>

        <div class="repair-status-layout__results" aria-live="polite">
          {searched.value && repairs.value.length === 0 ? (
            <p class="footer-muted repair-status-empty">{tStatic(locale, "repair.notFound")}</p>
          ) : null}

          {repairs.value.length > 0 ? (
            <RepairCards repairs={repairs.value} />
          ) : null}
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
