<?php

declare(strict_types=1);

namespace EzPhp\I18n;

use EzPhp\Contracts\TranslatorInterface;

/**
 * Class Translator
 *
 * Loads PHP array language files and resolves translation keys with placeholder replacement.
 *
 * Usage:
 *   $t = new Translator('de', 'en', '/path/to/lang');
 *   $t->get('validation.required', ['field' => 'E-Mail']); // "Das Feld E-Mail ist erforderlich."
 *
 * Key format: "<namespace>.<key>" where namespace maps to a lang file
 * and key supports dot-notation for nested arrays (e.g. "min.string").
 *
 * The second constructor parameter accepts either a single locale string or an ordered
 * list of fallback locales. Each locale in the chain is tried in order before the raw
 * key is returned.
 *
 * @package EzPhp\I18n
 */
final class Translator implements TranslatorInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /** @var list<string> */
    private readonly array $fallbackLocales;

    /**
     * Translator Constructor
     *
     * @param string               $locale          Active locale.
     * @param string|list<string>  $fallbackLocales Single fallback locale or ordered fallback chain.
     * @param string               $langPath        Absolute path to the lang/ directory.
     */
    public function __construct(
        private string $locale,
        string|array $fallbackLocales,
        private readonly string $langPath,
    ) {
        $this->fallbackLocales = is_string($fallbackLocales)
            ? [$fallbackLocales]
            : $fallbackLocales;
    }

    /**
     * Resolve a translation key with optional placeholder replacement.
     *
     * Resolution order:
     *   1. Active locale  → lang/<locale>/<namespace>.php
     *   2. Fallback locale → lang/<fallback>/<namespace>.php
     *   3. Raw $key returned as-is — never throws.
     *
     * Missing key convention: returns the raw $key string unchanged when the key
     * cannot be found in either locale. Keys without a dot separator (no namespace)
     * are returned immediately without any file lookup.
     *
     * @param array<string, string|int|float> $replacements
     */
    public function get(string $key, array $replacements = []): string
    {
        $dotPos = strpos($key, '.');

        if ($dotPos === false) {
            return $key;
        }

        $namespace = substr($key, 0, $dotPos);
        $subKey = substr($key, $dotPos + 1);

        $message = $this->resolveWithChain($namespace, $subKey) ?? $key;

        return $this->replace($message, $replacements);
    }

    /**
     * Resolve a pluralised translation key by count.
     *
     * The translation value must be a pipe-separated string of variants:
     *   'no apples|one apple|:count apples'
     *
     * Variant selection (zero-indexed):
     *   - count = 0        → variant[0]
     *   - count = 1        → variant[1] (or variant[last] if only one variant exists)
     *   - count >= 2       → variant[2] (or variant[last])
     *
     * The placeholder :count is automatically injected from $count unless overridden
     * in $replacements.
     *
     * @param array<string, string|int|float> $replacements
     */
    public function transChoice(string $key, int $count, array $replacements = []): string
    {
        $dotPos = strpos($key, '.');

        if ($dotPos === false) {
            return $key;
        }

        $namespace = substr($key, 0, $dotPos);
        $subKey = substr($key, $dotPos + 1);

        $message = $this->resolveWithChain($namespace, $subKey);

        if ($message === null) {
            return $key;
        }

        $parts = explode('|', $message);
        $index = min(max(0, $count), count($parts) - 1);

        if (!isset($replacements['count'])) {
            $replacements['count'] = $count;
        }

        return $this->replace($parts[$index], $replacements);
    }

    /**
     * @param string $locale
     *
     * @return void
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Returns the primary (first) fallback locale.
     *
     * @return string
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocales[0] ?? '';
    }

    /**
     * Returns the full ordered fallback locale chain.
     *
     * @return list<string>
     */
    public function getFallbackLocales(): array
    {
        return $this->fallbackLocales;
    }

    /**
     * Try the primary locale first, then each fallback in order.
     *
     * @param string $namespace
     * @param string $key
     *
     * @return string|null
     */
    private function resolveWithChain(string $namespace, string $key): ?string
    {
        $result = $this->resolve($namespace, $key, $this->locale);

        foreach ($this->fallbackLocales as $fallback) {
            if ($result !== null) {
                break;
            }

            $result = $this->resolve($namespace, $key, $fallback);
        }

        return $result;
    }

    /**
     * @param string $namespace
     * @param string $key
     * @param string $locale
     *
     * @return string|null
     */
    private function resolve(string $namespace, string $key, string $locale): ?string
    {
        return $this->navigate($this->loadMessages($namespace, $locale), $key);
    }

    /** @return array<string, mixed> */
    private function loadMessages(string $namespace, string $locale): array
    {
        $cacheKey = "$locale/{$namespace}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $file = $this->langPath . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $namespace . '.php';

        if (!file_exists($file)) {
            return $this->cache[$cacheKey] = [];
        }

        $data = require $file;

        if (!is_array($data)) {
            return $this->cache[$cacheKey] = [];
        }

        /** @var array<string, mixed> $data */
        return $this->cache[$cacheKey] = $data;
    }

    /** @param array<string, mixed> $messages */
    private function navigate(array $messages, string $key): ?string
    {
        $segments = explode('.', $key);
        $last = array_pop($segments);
        $current = $messages;

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $current) || !is_array($current[$segment])) {
                return null;
            }

            /** @var array<string, mixed> $current */
            $current = $current[$segment];
        }

        $value = $current[$last] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<string, string|int|float> $replacements */
    private function replace(string $message, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $message = str_replace(":$placeholder", (string) $value, $message);
        }

        return $message;
    }
}
