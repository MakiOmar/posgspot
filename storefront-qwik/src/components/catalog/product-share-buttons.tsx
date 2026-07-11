import { component$, useSignal, useVisibleTask$ } from "@builder.io/qwik";
import {
  FacebookIcon,
  LinkIcon,
  ShareIcon,
  WhatsappIcon,
  XIcon,
} from "~/components/icons";
import { tStatic, useI18n } from "~/lib/i18n/context";
import { toastError, toastSuccess } from "~/lib/notify";

interface ProductShareButtonsProps {
  /** Product display name used in share text. */
  title: string;
  /** Absolute canonical product URL (no query string). */
  url: string;
}

/**
 * PDP share row: native share (when available), copy link, WhatsApp, Facebook, X.
 */
export const ProductShareButtons = component$<ProductShareButtonsProps>(({ title, url }) => {
  const { locale } = useI18n();
  const canNativeShare = useSignal(false);
  const whatsappHref = `https://wa.me/?text=${encodeURIComponent(`${title} ${url}`)}`;
  const facebookHref = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
  const xHref = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(title)}`;

  // Detect Web Share API only in the browser (SSR has no navigator.share).
  // eslint-disable-next-line qwik/no-use-visible-task
  useVisibleTask$(() => {
    canNativeShare.value =
      typeof navigator !== "undefined" && typeof navigator.share === "function";
  });

  return (
    <div class="pdp-share">
      <p class="pdp-share__label">{tStatic(locale, "share.label")}</p>
      <div class="pdp-share__actions" role="group" aria-label={tStatic(locale, "share.label")}>
        {canNativeShare.value ? (
          <button
            type="button"
            class="pdp-share__btn"
            aria-label={tStatic(locale, "share.native")}
            title={tStatic(locale, "share.native")}
            onClick$={async () => {
              try {
                await navigator.share({ title, text: title, url });
              } catch (err) {
                if (err instanceof DOMException && err.name === "AbortError") {
                  return;
                }
              }
            }}
          >
            <ShareIcon size={18} />
          </button>
        ) : null}

        <button
          type="button"
          class="pdp-share__btn"
          aria-label={tStatic(locale, "share.copy")}
          title={tStatic(locale, "share.copy")}
          onClick$={async () => {
            try {
              await navigator.clipboard.writeText(url);
              await toastSuccess(tStatic(locale, "share.copied"));
            } catch {
              await toastError(tStatic(locale, "share.copyFailed"));
            }
          }}
        >
          <LinkIcon size={18} />
        </button>

        <a
          class="pdp-share__btn"
          href={whatsappHref}
          target="_blank"
          rel="noopener noreferrer"
          aria-label={tStatic(locale, "share.whatsapp")}
          title={tStatic(locale, "share.whatsapp")}
        >
          <WhatsappIcon size={18} />
        </a>

        <a
          class="pdp-share__btn"
          href={facebookHref}
          target="_blank"
          rel="noopener noreferrer"
          aria-label={tStatic(locale, "share.facebook")}
          title={tStatic(locale, "share.facebook")}
        >
          <FacebookIcon size={18} />
        </a>

        <a
          class="pdp-share__btn"
          href={xHref}
          target="_blank"
          rel="noopener noreferrer"
          aria-label={tStatic(locale, "share.x")}
          title={tStatic(locale, "share.x")}
        >
          <XIcon size={18} />
        </a>
      </div>
    </div>
  );
});
