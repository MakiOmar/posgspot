import { component$ } from "@builder.io/qwik";
import { Link, routeLoader$, useLocation, useNavigate, type DocumentHead } from "@builder.io/qwik-city";
import { DigitalGamePdp } from "~/components/digital/digital-game-pdp";
import { PageTitleBar } from "~/components/layout/page-title-bar";
import { ApiError, fetchDigitalGame, fetchDigitalGames } from "~/lib/api";
import { type DigitalPlatform } from "~/lib/digital-game";
import { isSupportedLocale } from "~/lib/i18n/config";
import { tStatic } from "~/lib/i18n/context";
import { localePath } from "~/lib/i18n/paths";
import { publicSeoLinks } from "~/lib/seo-hreflang";
import { withStorefrontThemeHead } from "~/lib/storefront-head";
import type { DigitalGameSummary, DigitalSkus } from "~/lib/types";
import { normalizeWhatsAppDigits } from "~/lib/whatsapp";
import { useLangParam, useSiteSettings } from "~/routes/[lang]/layout";

const emptySkus: DigitalSkus = { primary: null, secondary: null, gift_card: null };

export const useGameDetail = routeLoader$(async ({ params, query }) => {
  const locale = isSupportedLocale(params.lang) ? params.lang : "en";
  const id = Number(params.id);
  const platform = (query.get("platform") === "5" ? "5" : "4") as DigitalPlatform;

  if (!Number.isFinite(id) || id <= 0) {
    return {
      ok: false as const,
      notFound: true,
      error: "invalid",
      platform,
      game: null,
      skus: emptySkus,
      alsoBought: [] as DigitalGameSummary[],
    };
  }

  try {
    // Parallel: detail + also-bought list (no waterfall).
    const [detailRes, listRes] = await Promise.all([
      fetchDigitalGame(id, locale),
      fetchDigitalGames(platform, 1, locale).catch(() => null),
    ]);

    const games = (listRes?.data.games ?? []) as DigitalGameSummary[];
    const alsoBought = games.filter((g) => Number(g.id) !== id).slice(0, 4);

    return {
      ok: true as const,
      game: detailRes.data.game as Record<string, unknown>,
      skus: detailRes.data.skus,
      platform,
      alsoBought,
    };
  } catch (e) {
    const status = e instanceof ApiError ? e.status : 0;
    const message = e instanceof Error ? e.message : "Failed to load game";
    return {
      ok: false as const,
      notFound: status === 404,
      error: message,
      platform,
      game: null,
      skus: emptySkus,
      alsoBought: [] as DigitalGameSummary[],
    };
  }
});

export default component$(() => {
  const detail = useGameDetail();
  const settings = useSiteSettings();
  const nav = useNavigate();
  const loc = useLocation();
  const lang = (loc.params.lang || "en") as "en" | "ar";

  if (!detail.value.ok || !detail.value.game) {
    return (
      <article>
        <PageTitleBar
          title={
            detail.value.notFound
              ? tStatic(lang, "digital.gameNotFound")
              : tStatic(lang, "digital.gameLoadFailed")
          }
          crumbs={[
            { label: tStatic(lang, "nav.games"), href: "/games" },
            {
              label: detail.value.notFound
                ? tStatic(lang, "digital.gameNotFound")
                : tStatic(lang, "digital.gameLoadFailed"),
            },
          ]}
        />
        <div class="empty-state" style={{ marginTop: "2rem" }}>
          <p>
            {detail.value.notFound
              ? tStatic(lang, "digital.gameNotFound")
              : tStatic(lang, "digital.gameLoadFailed")}
          </p>
          <p>
            <Link href={localePath(lang, "/games")} class="btn btn-secondary">
              {tStatic(lang, "nav.games")}
            </Link>{" "}
            {!detail.value.notFound ? (
              <button
                type="button"
                class="btn btn-primary"
                onClick$={() => nav(loc.url.pathname + loc.url.search)}
              >
                {tStatic(lang, "digital.retry")}
              </button>
            ) : null}
          </p>
        </div>
      </article>
    );
  }

  const digital = settings.value.digital;
  const faqs = Array.isArray(digital?.pdp_faqs) ? digital.pdp_faqs : [];
  const askWhatsApp =
    normalizeWhatsAppDigits(digital?.ask_whatsapp) ||
    normalizeWhatsAppDigits(settings.value.contact?.whatsapp) ||
    null;

  return (
    <DigitalGamePdp
      game={detail.value.game}
      skus={detail.value.skus}
      platform={detail.value.platform}
      alsoBought={detail.value.alsoBought}
      faqs={faqs}
      askWhatsApp={askWhatsApp}
      currency={settings.value.currency}
    />
  );
});

export const head: DocumentHead = ({ resolveValue, url }) => {
  const detail = resolveValue(useGameDetail);
  const settings = resolveValue(useSiteSettings);
  const lang = resolveValue(useLangParam);
  const game = detail.game;
  const titleRaw = game ? String(game.title ?? "") : "";
  const title = game
    ? `${titleRaw} — ${settings.business_name}`
    : `${tStatic(lang, "digital.gameNotFound")} — ${settings.business_name}`;
  const description = game
    ? tStatic(lang, "digital.gameDetailDescription", { title: titleRaw })
    : tStatic(lang, "digital.gameNotFound");
  const path = game ? `/games/${String(game.id)}` : "/games";
  const image =
    game &&
    (detail.platform === "5"
      ? String(game.ps5_image_url ?? game.image_url ?? "")
      : String(game.ps4_image_url ?? game.image_url ?? ""));

  return withStorefrontThemeHead(settings, {
    title,
    meta: [
      { name: "description", content: description },
      { property: "og:title", content: title },
      { property: "og:description", content: description },
      ...(image ? [{ property: "og:image", content: image }] : []),
    ],
    links: publicSeoLinks(url.origin, path, lang),
  });
};
