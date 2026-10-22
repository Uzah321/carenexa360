// Printable records (care plans, medical histories) are built as one
// self-contained HTML document. The same markup feeds both exports: printed
// via the browser (where "Save as PDF" is the PDF route), and downloaded as
// a .doc, which Word opens natively as HTML — so neither needs a PDF or DOCX
// library.

export function escapeHtml(value: unknown): string {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

export const DOCUMENT_STYLES = `
  @page { size: A4; margin: 16mm 14mm; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10.5pt; color: #1a1a1a; line-height: 1.4; }
  h1 { font-size: 18pt; margin: 0 0 4pt; }
  h2 { font-size: 13pt; margin: 18pt 0 6pt; padding-bottom: 3pt; border-bottom: 2px solid #0f766e; color: #0f766e; }
  h3 { font-size: 11pt; margin: 12pt 0 4pt; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
  th, td { border: 1px solid #c8c8c8; padding: 4pt 6pt; vertical-align: top; text-align: left; }
  table.kv th { width: 32%; background: #f3f4f6; font-weight: bold; }
  table.grid thead th { background: #f3f4f6; }
  ul, ol { margin: 0; padding-left: 14pt; }
  p { margin: 0 0 3pt; }
  mark { background: #fde68a; }
  small { color: #555; }
  .meta { color: #555; margin-bottom: 8pt; }
  .block { page-break-inside: avoid; break-inside: avoid; }
  .rating { font-weight: bold; }
  .rating.low { color: #3f7d20; }
  .rating.medium { color: #b45309; }
  .rating.high, .rating.very_high { color: #b91c1c; }
  .signatures td { height: 34pt; }
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
`;

/** Wraps a body in the document shell Word and the print dialog both expect. */
export function htmlDocument(title: string, body: string, extraStyles = ""): string {
  return `<!DOCTYPE html>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="utf-8">
<title>${escapeHtml(title)}</title>
<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->
<style>${DOCUMENT_STYLES}${extraStyles}</style>
</head>
<body>
${body}
</body>
</html>`;
}

/** A lowercase, hyphenated file name from free text — "Medical history — Ruth" → "medical-history-ruth". */
export function fileSlug(text: string): string {
  return text.toLowerCase().replace(/[^a-z0-9-]+/g, "-");
}

// Prints via a hidden iframe so the app's own layout/CSS doesn't leak into
// the document. The browser's print dialog offers "Save as PDF".
export function printHtmlDocument(html: string, fileBaseName: string): void {
  const iframe = document.createElement("iframe");
  iframe.setAttribute("aria-hidden", "true");
  iframe.style.cssText = "position:fixed;right:0;bottom:0;width:0;height:0;border:0;";
  document.body.appendChild(iframe);

  const doc = iframe.contentDocument;
  const win = iframe.contentWindow;
  if (!doc || !win) {
    iframe.remove();
    return;
  }

  doc.open();
  doc.write(html);
  doc.close();
  // The print dialog's default filename comes from the document title.
  doc.title = fileBaseName;

  const cleanup = () => setTimeout(() => iframe.remove(), 500);
  win.addEventListener("afterprint", cleanup, { once: true });
  setTimeout(() => {
    win.focus();
    win.print();
  }, 50);
}

export function downloadWordDocument(html: string, fileBaseName: string): void {
  // A leading BOM makes Word pick up UTF-8 rather than guessing the encoding.
  const blob = new Blob(["﻿", html], { type: "application/msword" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${fileBaseName}.doc`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
