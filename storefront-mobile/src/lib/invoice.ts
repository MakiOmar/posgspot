import * as Print from "expo-print";
import * as Sharing from "expo-sharing";
import { File, Paths } from "expo-file-system";
import { API_BASE } from "./config";

/**
 * POS invoice URLs are tokenized HTML receipts, often with print_on_load=true.
 * Downloads must stay on the Laravel origin and skip auto-print.
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

/** Make fetched POS HTML renderable as PDF (absolute assets, hide POS print chrome). */
export function htmlForPrint(html: string, pageUrl: string): string {
  const origin = new URL(pageUrl).origin;
  const inject = `<base href="${origin}/"><style>.no-print,#print_invoice{display:none!important}</style>`;
  if (/<head[^>]*>/i.test(html)) {
    return html.replace(/<head[^>]*>/i, (match) => `${match}${inject}`);
  }
  return `${inject}${html}`;
}

function safePdfBaseName(name: string | undefined): string {
  const cleaned = (name || "invoice")
    .trim()
    .replace(/[^\w.-]+/g, "_")
    .replace(/^_+|_+$/g, "")
    .slice(0, 64);
  return cleaned || "invoice";
}

export type InvoiceDownloadResult = "shared" | "invalid" | "unavailable";

/**
 * Fetch the POS HTML invoice, render it to a local PDF, and open the system
 * share/save sheet (Downloads / Files). No in-app WebView browse/print.
 */
export async function downloadInvoicePdf(
  rawUrl: string,
  fileBaseName?: string,
): Promise<InvoiceDownloadResult> {
  const url = invoiceViewUrl(rawUrl);
  if (!url) {
    return "invalid";
  }

  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`invoice fetch failed (${response.status})`);
  }

  const html = htmlForPrint(await response.text(), url);
  const { uri } = await Print.printToFileAsync({ html });

  const base = safePdfBaseName(fileBaseName);
  const dest = new File(Paths.cache, `${base}.pdf`);
  if (dest.exists) {
    dest.delete();
  }
  // Copy so the share sheet shows a stable *.pdf name.
  await new File(uri).copy(dest);

  if (!(await Sharing.isAvailableAsync())) {
    return "unavailable";
  }

  await Sharing.shareAsync(dest.uri, {
    mimeType: "application/pdf",
    UTI: "com.adobe.pdf",
    dialogTitle: `${base}.pdf`,
  });

  return "shared";
}
