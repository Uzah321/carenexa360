// Mirrors api/app/Support/RichText.php — the same allowlist of formatting
// tags with no attributes. The API sanitises on save; this runs again before
// anything is rendered as HTML, so a bad value can never reach the page.

const ALLOWED_TAGS = new Set(["p", "br", "div", "b", "strong", "i", "em", "u", "mark", "ul", "ol", "li"]);
const DROP_WITH_CONTENT = new Set(["script", "style", "iframe", "object", "embed", "template", "head", "title"]);

function escapeText(text: string): string {
  return text.replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;");
}

function render(node: Node): string {
  if (node.nodeType === Node.TEXT_NODE) return escapeText(node.textContent ?? "");
  if (!(node instanceof Element)) return "";

  let tag = node.tagName.toLowerCase();
  if (DROP_WITH_CONTENT.has(tag)) return "";

  const inner = Array.from(node.childNodes).map(render).join("");

  // The editor's highlight comes through as a background-coloured span.
  if (tag === "span" && /background(-color)?\s*:/i.test(node.getAttribute("style") ?? "")) tag = "mark";
  // Some browsers emit styled spans for bold/italic/underline instead of tags.
  if (tag === "span") {
    const style = node.getAttribute("style") ?? "";
    if (/font-weight\s*:\s*(bold|[6-9]00)/i.test(style)) return `<b>${inner}</b>`;
    if (/font-style\s*:\s*italic/i.test(style)) return `<i>${inner}</i>`;
    if (/text-decoration[^;]*underline/i.test(style)) return `<u>${inner}</u>`;
  }

  if (!ALLOWED_TAGS.has(tag)) return inner;
  return tag === "br" ? "<br>" : `<${tag}>${inner}</${tag}>`;
}

export function sanitizeRichText(html: string | null | undefined): string {
  if (!html) return "";
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, "text/html");
  return Array.from(doc.body.childNodes).map(render).join("").trim();
}

export function richTextIsEmpty(html: string | null | undefined): boolean {
  if (!html) return true;
  const doc = new DOMParser().parseFromString(`<body>${html}</body>`, "text/html");
  return (doc.body.textContent ?? "").trim() === "";
}

/**
 * Safe HTML for display. Values saved before a field became rich text are
 * plain text, so those are escaped with their line breaks kept.
 */
export function richTextToHtml(value: string | null | undefined): string {
  if (!value) return "";
  if (!/<[a-z][\s\S]*>/i.test(value)) return escapeText(value).replaceAll("\n", "<br>");
  return sanitizeRichText(value);
}
