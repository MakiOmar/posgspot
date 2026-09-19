import type { StoreLocaleCode } from "./config";
import en from "../../i18n/en.json";

type MessageTree = Record<string, unknown>;

/** EN is always available for SSR fallback; AR loads on demand. */
const BUNDLES: Partial<Record<StoreLocaleCode, MessageTree>> = { en };
let arLoad: Promise<MessageTree> | null = null;

/**
 * Ensure locale message tree is loaded (call from layout loaders / visible tasks).
 * Keeps AR JSON out of the default EN client graph until needed.
 */
export async function ensureLocaleMessages(locale: StoreLocaleCode): Promise<void> {
  if (locale !== "ar") {
    return;
  }
  if (BUNDLES.ar) {
    return;
  }
  if (!arLoad) {
    arLoad = import("../../i18n/ar.json").then((m) => {
      const tree = (m as { default: MessageTree }).default;
      BUNDLES.ar = tree;
      return tree;
    });
  }
  await arLoad;
}

function resolvePath(tree: MessageTree, key: string): string | undefined {
  const parts = key.split(".");
  let node: unknown = tree;
  for (const part of parts) {
    if (node == null || typeof node !== "object") {
      return undefined;
    }
    node = (node as MessageTree)[part];
  }
  return typeof node === "string" ? node : undefined;
}

export function translate(
  locale: StoreLocaleCode,
  key: string,
  params?: Record<string, string | number>,
): string {
  const bundle = BUNDLES[locale] ?? BUNDLES.en!;
  let text = resolvePath(bundle, key) ?? resolvePath(BUNDLES.en!, key) ?? key;

  if (params) {
    for (const [name, value] of Object.entries(params)) {
      text = text.replaceAll(`{${name}}`, String(value));
    }
  }

  return text;
}

export function messagesFor(locale: StoreLocaleCode): MessageTree {
  return BUNDLES[locale] ?? BUNDLES.en!;
}
