import type { StoreLocaleCode } from "./config";
import en from "../../i18n/en.json";
import ar from "../../i18n/ar.json";

type MessageTree = Record<string, unknown>;

const BUNDLES: Record<StoreLocaleCode, MessageTree> = { en, ar };

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
  const bundle = BUNDLES[locale] ?? BUNDLES.en;
  let text = resolvePath(bundle, key) ?? resolvePath(BUNDLES.en, key) ?? key;

  if (params) {
    for (const [name, value] of Object.entries(params)) {
      text = text.replaceAll(`{${name}}`, String(value));
    }
  }

  return text;
}

export function messagesFor(locale: StoreLocaleCode): MessageTree {
  return BUNDLES[locale] ?? BUNDLES.en;
}
