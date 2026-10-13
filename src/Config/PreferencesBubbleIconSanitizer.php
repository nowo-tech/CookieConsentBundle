<?php

declare(strict_types=1);

namespace Nowo\CookieConsentBundle\Config;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use InvalidArgumentException;

use function htmlspecialchars;
use function in_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function trim;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const LIBXML_NONET;

/**
 * Allowlist sanitizer for the preferences bubble icon markup (rendered with |raw on every public page).
 *
 * The markup is parsed as XML (inline SVG or simple inline HTML such as an emoji `<span>`) and re-serialized
 * from the DOM: only allowlisted elements and attributes survive, text is escaped, and comments / processing
 * instructions are dropped. Disallowed elements, event handler attributes, `href` values that are not local
 * fragment references (`#id`) and script-capable URL values are rejected with an exception.
 */
final class PreferencesBubbleIconSanitizer
{
    public const DEMO_EMOJI_ICON_HTML = '<span class="nowo-cookie-consent__preferences-bubble-emoji" aria-hidden="true">🍪</span>';

    private const SVG_NAMESPACE   = 'http://www.w3.org/2000/svg';
    private const XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';
    private const XHTML_NAMESPACE = 'http://www.w3.org/1999/xhtml';

    /** SVG elements (lowercase local names) allowed in the icon. */
    private const SVG_ELEMENTS = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon', 'title', 'desc',
        'defs', 'symbol', 'use', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'text', 'tspan',
    ];

    /** Inline HTML elements (lowercase) allowed in the icon (e.g. an emoji wrapped in a span). */
    private const HTML_ELEMENTS = ['span', 'i', 'b', 'strong', 'em', 'small'];

    /** Attributes (lowercase) kept on allowed elements; anything else (except dangerous ones, which throw) is dropped. */
    private const ATTRIBUTES = [
        'class', 'id', 'role', 'aria-hidden', 'aria-label', 'focusable', 'viewbox', 'width', 'height', 'version',
        'preserveaspectratio', 'fill', 'fill-rule', 'fill-opacity', 'stroke', 'stroke-width', 'stroke-linecap',
        'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity',
        'clip-rule', 'clip-path', 'mask', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'x', 'y', 'x1', 'y1',
        'x2', 'y2', 'dx', 'dy', 'points', 'transform', 'opacity', 'offset', 'stop-color', 'stop-opacity',
        'gradientunits', 'gradienttransform', 'spreadmethod', 'maskunits', 'clippathunits', 'vector-effect',
        'shape-rendering', 'text-anchor', 'font-size', 'font-family', 'font-weight', 'color', 'href',
    ];

    /**
     * @param string|null $html Raw icon markup from configuration
     *
     * @throws InvalidArgumentException When the markup is malformed or contains disallowed content
     *
     * @return string|null Sanitized markup or null when empty
     */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = trim($html);

        if ($html === '') {
            return null;
        }

        $document       = new DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadXML(
                '<root xmlns:xlink="' . self::XLINK_NAMESPACE . '">' . $html . '</root>',
                LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            throw new InvalidArgumentException('Invalid preferences bubble icon markup.');
        }

        $sanitized = trim(self::serializeChildren($document->documentElement));

        return $sanitized !== '' ? $sanitized : null;
    }

    /**
     * Like {@see sanitize()} but returns null instead of throwing (render-time guard for stored values).
     *
     * @param string|null $html Raw icon markup
     *
     * @return string|null Sanitized markup, or null when empty or invalid
     */
    public static function sanitizeOrNull(?string $html): ?string
    {
        try {
            return self::sanitize($html);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private static function serializeChildren(DOMNode $parent): string
    {
        $out = '';
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $out .= self::serializeElement($child);
            } elseif ($child instanceof DOMText) {
                // Text and CDATA sections are emitted as escaped text.
                $out .= self::escape($child->data);
            }
            // Comments and processing instructions are dropped.
        }

        return $out;
    }

    private static function serializeElement(DOMElement $element): string
    {
        $name      = (string) $element->localName;
        $lowerName = strtolower($name);
        $namespace = $element->namespaceURI;

        $isSvg = in_array($lowerName, self::SVG_ELEMENTS, true)
            && ($namespace === null || $namespace === self::SVG_NAMESPACE);
        $isHtml = in_array($lowerName, self::HTML_ELEMENTS, true)
            && ($namespace === null || $namespace === self::XHTML_NAMESPACE);

        if (!$isSvg && !$isHtml) {
            throw new InvalidArgumentException('Invalid preferences bubble icon markup.');
        }

        $out = '<' . $name;
        if ($lowerName === 'svg' && $namespace === self::SVG_NAMESPACE) {
            $out .= ' xmlns="' . self::SVG_NAMESPACE . '"';
        }

        foreach ($element->attributes as $attribute) {
            $attributeName = strtolower((string) $attribute->localName);
            $value         = $attribute->value;

            if (str_starts_with($attributeName, 'on') || self::isDangerousValue($value)) {
                throw new InvalidArgumentException('Invalid preferences bubble icon markup.');
            }

            $isXlink = $attribute->namespaceURI === self::XLINK_NAMESPACE;
            if ($attribute->namespaceURI !== null && !$isXlink) {
                continue;
            }
            if (!in_array($attributeName, self::ATTRIBUTES, true)) {
                continue;
            }
            if ($attributeName === 'href' && preg_match('/^#[A-Za-z_][\w.:-]*$/', $value) !== 1) {
                throw new InvalidArgumentException('Invalid preferences bubble icon markup.');
            }

            $out .= ' ' . ($isXlink ? 'xlink:' : '') . $attribute->localName . '="' . self::escape($value) . '"';
        }

        $children = self::serializeChildren($element);
        if ($children === '' && $isSvg) {
            return $out . '/>';
        }

        return $out . '>' . $children . '</' . $name . '>';
    }

    /**
     * Rejects script-capable URL schemes and url() references that are not local fragments.
     */
    private static function isDangerousValue(string $value): bool
    {
        $normalized = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $value));

        foreach (['javascript:', 'vbscript:', 'data:', 'expression('] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        if (str_contains($normalized, 'url(')) {
            $all   = preg_match_all('/url\(/', $normalized);
            $local = preg_match_all('/url\([\'"]?#[\w.:-]+[\'"]?\)/', $normalized);

            return $all !== $local;
        }

        return false;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
