<?php

namespace App\Services\Forms;

/**
 * Rewrites a form's custom CSS so every rule only matches inside that
 * form's wrapper (`#ff-form-{id}`) — custom CSS can restyle the form, never
 * the surrounding public site or admin UI. See docs/FORM_BUILDER.md §Custom
 * CSS handling.
 *
 * A small purpose-built scanner (string- and paren-aware), not a regex
 * find/replace:
 *   - every selector in a selector list gets the scope prepended
 *     (`.title, h3` -> `#ff-form-1 .title, #ff-form-1 h3`);
 *     `:root`/`html`/`body` at the start of a selector are replaced BY the
 *     scope, so "page-level" rules land on the form wrapper instead;
 *   - `@media`/`@supports`/`@container` blocks are kept and their inner
 *     rules scoped recursively; `@keyframes` are kept as-is (they don't
 *     select elements);
 *   - every other at-rule (`@import`, `@font-face`, `@page`, `@namespace`,
 *     `@layer`, `@property`, ...) is dropped;
 *   - nested rule blocks inside a declaration block are dropped
 *     (declarations only), so CSS nesting can't be used to escape the scope;
 *   - `<` is escaped (so `</style>` can never close the style element) and
 *     legacy script-in-CSS vectors (`expression(`, `javascript:`,
 *     `behavior:`, `-moz-binding`) are neutralized.
 *
 * Applied at render time on the raw stored CSS, so improving this class
 * re-protects every existing form without a data migration.
 */
final class FormCssScoper
{
    public const MAX_LENGTH = 20000;

    private const NESTING_AT_RULES = ['media', 'supports', 'container'];

    private const PASSTHROUGH_AT_RULES = ['keyframes', '-webkit-keyframes'];

    public function scope(?string $css, string $scope): string
    {
        if ($css === null || trim($css) === '') {
            return '';
        }

        $css = substr($css, 0, self::MAX_LENGTH);
        $css = $this->stripComments($css);
        $css = $this->neutralize($css);

        return trim($this->scopeRules($css, $scope));
    }

    private function scopeRules(string $css, string $scope): string
    {
        $out = [];
        $length = strlen($css);
        $i = 0;

        while ($i < $length) {
            [$prelude, $i, $terminator] = $this->readUntil($css, $i, ['{', ';', '}']);
            $prelude = trim($prelude);

            if ($terminator !== '{') {
                // `@import ...;`, `@charset ...;`, a stray `}` or trailing
                // text without a block: nothing safe to keep.
                continue;
            }

            [$body, $i] = $this->readBlock($css, $i);

            if ($prelude === '') {
                continue;
            }

            if ($prelude[0] === '@') {
                $name = strtolower((string) preg_replace('/^@([a-zA-Z-]+).*$/s', '$1', $prelude));

                if (in_array($name, self::NESTING_AT_RULES, true)) {
                    $inner = $this->scopeRules($body, $scope);
                    if ($inner !== '') {
                        $out[] = $prelude.'{'.$inner.'}';
                    }
                } elseif (in_array($name, self::PASSTHROUGH_AT_RULES, true)) {
                    $out[] = $prelude.'{'.$body.'}';
                }

                continue; // any other at-rule is dropped
            }

            $selectors = $this->scopeSelectorList($prelude, $scope);
            if ($selectors !== '') {
                $out[] = $selectors.'{'.$this->declarationsOnly($body).'}';
            }
        }

        return implode("\n", $out);
    }

    private function scopeSelectorList(string $selectorList, string $scope): string
    {
        $scoped = [];
        foreach ($this->splitTopLevel($selectorList, ',') as $selector) {
            $selector = trim($selector);
            if ($selector === '') {
                continue;
            }

            // `:root`, `html`, `body` (possibly chained: `html body`) at the
            // start of a selector stand for "the page" -- map them to the form.
            if (preg_match('/^(?:(?::root|html|body)(?![\w-])\s*)+/i', $selector, $match)) {
                $rest = substr($selector, strlen($match[0]));
                // `body .x` (descendant) keeps its space; `body.dark` (compound) doesn't.
                $isDescendant = $match[0] !== rtrim($match[0]);
                $scoped[] = $rest === '' ? $scope : ($isDescendant ? $scope.' '.$rest : $scope.$rest);
            } elseif (str_starts_with($selector, $scope) && ! preg_match('/^[\w-]/', substr($selector, strlen($scope)))) {
                $scoped[] = $selector; // already scoped to this exact form
            } else {
                $scoped[] = $scope.' '.$selector;
            }
        }

        return implode(', ', $scoped);
    }

    /** Keeps the declarations of a rule body, dropping any nested `selector { ... }` blocks. */
    private function declarationsOnly(string $body): string
    {
        $kept = '';
        $length = strlen($body);
        $i = 0;

        while ($i < $length) {
            [$chunk, $i, $terminator] = $this->readUntil($body, $i, ['{', ';']);
            if ($terminator === '{') {
                [, $i] = $this->readBlock($body, $i); // drop the nested block and its prelude

                continue;
            }
            if (trim($chunk) !== '') {
                $kept .= trim($chunk).';';
            }
        }

        return $kept;
    }

    /**
     * Reads from $i up to the first terminator char that is outside a
     * string. Returns [text, index after the terminator, terminator|null].
     *
     * @param  list<string>  $terminators
     * @return array{0: string, 1: int, 2: string|null}
     */
    private function readUntil(string $css, int $i, array $terminators): array
    {
        $start = $i;
        $length = strlen($css);
        $quote = null;

        for (; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;

                continue;
            }
            if (in_array($char, $terminators, true)) {
                return [substr($css, $start, $i - $start), $i + 1, $char];
            }
        }

        return [substr($css, $start), $length, null];
    }

    /**
     * $i points just past an opening `{`. Returns [body, index after the
     * matching `}`]; an unbalanced block consumes the rest of the input.
     *
     * @return array{0: string, 1: int}
     */
    private function readBlock(string $css, int $i): array
    {
        $start = $i;
        $length = strlen($css);
        $depth = 1;
        $quote = null;

        for (; $i < $length; $i++) {
            $char = $css[$i];
            if ($quote !== null) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                return [substr($css, $start, $i - $start), $i + 1];
            }
        }

        return [substr($css, $start), $length];
    }

    /** Splits on $separator outside strings, (), and []. @return list<string> */
    private function splitTopLevel(string $text, string $separator): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $current = '';
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $text[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif (($char === ')' || $char === ']') && $depth > 0) {
                $depth--;
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = $current;
                $current = '';

                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }

    private function stripComments(string $css): string
    {
        return (string) preg_replace('#/\*.*?(\*/|$)#s', '', $css);
    }

    private function neutralize(string $css): string
    {
        // `\3c ` is the CSS escape for `<`: still renders as "<" inside a
        // `content:` string, but can never form a `</style>` tag.
        $css = str_replace('<', '\\3c ', $css);

        return (string) preg_replace(
            '/expression\s*\(|javascript\s*:|vbscript\s*:|behavior\s*:|-moz-binding/i',
            'blocked',
            $css
        );
    }
}
