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
 * @package EzPhp\I18n
 */
final class Translator implements TranslatorInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $cache = [];

    /**
     * Translator Constructor
     *
     * @param string $locale
     * @param string $fallbackLocale
     * @param string $langPath
     */
    public function __construct(
        private string $locale,
        private readonly string $fallbackLocale,
        private readonly string $langPath,
    ) {
    }

    /**
     * Resolve a translation key with optional placeholder replacement.
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

        $message = $this->resolve($namespace, $subKey, $this->locale)
            ?? $this->resolve($namespace, $subKey, $this->fallbackLocale)
            ?? $key;

        return $this->replace($message, $replacements);
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
     * @return string
     */
    public function getFallbackLocale(): string
    {
        return $this->fallbackLocale;
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
