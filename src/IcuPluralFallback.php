<?php

declare(strict_types=1);

namespace EzPhp\I18n;

/**
 * Class IcuPluralFallback
 *
 * Minimal reader for ICU plural messages (`{count, plural, =0 {…} one {…} other {…}}`)
 * for when ext-intl is not loaded: picks the `=N` branch for an exact match, else
 * `one` for 1, else `other`, and replaces `#` with the count. It knows no locale
 * plural rules (few/many/two/zero are ignored) — install ext-intl for those.
 *
 * @internal Used by Translator::transChoice().
 * @package EzPhp\I18n
 */
final class IcuPluralFallback
{
    /**
     * @param string $message
     * @param int    $count
     *
     * @return string|null Null when the message is not a well-formed ICU plural with an `other` branch.
     */
    public static function format(string $message, int $count): ?string
    {
        if (preg_match('/^\s*\{\s*\w+\s*,\s*plural\s*,(.*)\}\s*$/s', $message, $m) !== 1) {
            return null;
        }

        $branches = self::branches($m[1]);

        if ($branches === null || !isset($branches['other'])) {
            return null;
        }

        $text = $branches['=' . $count] ?? ($count === 1 ? ($branches['one'] ?? null) : null) ?? $branches['other'];

        return str_replace('#', (string) $count, $text);
    }

    /**
     * Split `sel1 {text} sel2 {text}` into selector → text, honouring nested braces.
     *
     * @param string $body
     *
     * @return array<string, string>|null
     */
    private static function branches(string $body): ?array
    {
        $branches = [];
        $length = strlen($body);
        $i = 0;

        while (true) {
            while ($i < $length && ctype_space($body[$i])) {
                $i++;
            }

            if ($i >= $length) {
                return $branches;
            }

            $start = $i;

            while ($i < $length && !ctype_space($body[$i]) && $body[$i] !== '{') {
                $i++;
            }

            $selector = substr($body, $start, $i - $start);

            while ($i < $length && ctype_space($body[$i])) {
                $i++;
            }

            if ($selector === '' || $i >= $length || $body[$i] !== '{') {
                return null;
            }

            $depth = 0;
            $open = $i;

            for (; $i < $length; $i++) {
                if ($body[$i] === '{') {
                    $depth++;
                } elseif ($body[$i] === '}' && --$depth === 0) {
                    break;
                }
            }

            if ($i >= $length) {
                return null;
            }

            $branches[$selector] = substr($body, $open + 1, $i - $open - 1);
            $i++;
        }
    }
}
