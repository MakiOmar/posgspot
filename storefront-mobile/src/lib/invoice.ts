import { API_BASE } from "./config";

type InvoiceRouter = {
  push: (href: never) => void;
};

/**
 * POS invoice URLs are tokenized HTML receipts, often with print_on_load=true.
 * The in-app viewer must stay on the Laravel origin and skip auto-print.
 */
export function invoiceViewUrl(raw: string): string | null {
  try {
    const url = new URL(raw);
    const api = new URL(API_BASE);
    const okProtocol =
      url.protocol === "https:" || (__DEV__ && url.protocol === "http:");
    if (!okProtocol || url.hostname !== api.hostname) {
      return null;
    }
    url.searchParams.delete("print_on_load");
    return url.toString();
  } catch {
    return null;
  }
}

/** Push the in-app invoice screen. Returns false if the URL is not allowed. */
export function openInvoice(router: InvoiceRouter, rawUrl: string): boolean {
  const url = invoiceViewUrl(rawUrl);
  if (!url) {
    return false;
  }
  router.push(`/account/invoice?url=${encodeURIComponent(url)}` as never);
  return true;
}

/** Make fetched POS HTML printable (absolute assets, hide POS print chrome). */
export function htmlForPrint(html: string, pageUrl: string): string {
  const origin = new URL(pageUrl).origin;
  const inject = `<base href="${origin}/"><style>.no-print,#print_invoice{display:none!important}</style>`;
  if (/<head[^>]*>/i.test(html)) {
    return html.replace(/<head[^>]*>/i, (match) => `${match}${inject}`);
  }
  return `${inject}${html}`;
}
