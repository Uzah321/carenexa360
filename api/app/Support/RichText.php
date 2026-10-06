<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans rich text from the web editor down to a small allowlist of
 * formatting tags with no attributes, so it is safe to render as HTML in the
 * app and in printed / Word exports. Anything else — scripts, styles, links,
 * event handlers, unknown tags — is dropped, keeping only its text.
 *
 * Mirrored in web/src/lib/richText.ts, which runs the same allowlist before
 * rendering (defence in depth) — keep the two in step.
 */
class RichText
{
    public const ALLOWED_TAGS = ['p', 'br', 'div', 'b', 'strong', 'i', 'em', 'u', 'mark', 'ul', 'ol', 'li'];

    /** Tags whose content is dropped along with the tag. */
    protected const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'head', 'title'];

    public static function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The XML encoding hint stops DOMDocument from reading UTF-8 as Latin-1.
        $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $doc->getElementsByTagName('body')->item(0);
        if (! $body) {
            return null;
        }

        $out = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $out .= self::render($child);
        }

        $out = trim($out);

        // An editor left with only empty markup (e.g. "<br>") holds no content.
        return trim(strip_tags($out)) === '' ? null : $out;
    }

    protected static function render(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (! $node instanceof DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            return '';
        }

        $inner = '';
        foreach (iterator_to_array($node->childNodes) as $child) {
            $inner .= self::render($child);
        }

        // The editor's highlight comes through as a background-coloured span.
        if ($tag === 'span' && preg_match('/background(-color)?\s*:/i', $node->getAttribute('style'))) {
            $tag = 'mark';
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            return $inner;
        }

        return $tag === 'br' ? '<br>' : "<{$tag}>{$inner}</{$tag}>";
    }
}
