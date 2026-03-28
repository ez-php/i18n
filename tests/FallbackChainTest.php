<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\I18n\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class FallbackChainTest
 *
 * Tests the fallback locale chain: Translator accepts an ordered list of
 * fallback locales and tries each in turn before returning the raw key.
 *
 * @package Tests\I18n
 */
#[CoversClass(Translator::class)]
final class FallbackChainTest extends TestCase
{
    private string $langPath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->langPath = sys_get_temp_dir() . '/ez-i18n-chain-' . uniqid();

        mkdir($this->langPath . '/de_AT', 0o755, true);
        mkdir($this->langPath . '/de', 0o755, true);
        mkdir($this->langPath . '/en', 0o755, true);

        // de_AT: only a regional key
        file_put_contents(
            $this->langPath . '/de_AT/messages.php',
            "<?php\nreturn ['regional' => 'Österreich'];\n",
        );

        // de: a shared key that shadows the English version
        file_put_contents(
            $this->langPath . '/de/messages.php',
            "<?php\nreturn ['common' => 'Deutsch', 'de_only' => 'Nur Deutsch'];\n",
        );

        // en: ultimate fallback with all keys
        file_put_contents(
            $this->langPath . '/en/messages.php',
            "<?php\nreturn ['common' => 'English Common', 'fallback' => 'English Only'];\n",
        );
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $this->removeDir($this->langPath);
    }

    /**
     * @param string $path
     *
     * @return void
     */
    private function removeDir(string $path): void
    {
        foreach (glob($path . '/{,.}*', GLOB_BRACE) ?: [] as $entry) {
            if (in_array(basename($entry), ['.', '..'], true)) {
                continue;
            }

            is_dir($entry) ? $this->removeDir($entry) : unlink($entry);
        }

        rmdir($path);
    }

    // ─── Primary locale wins ─────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_primary_locale_key_is_found_first(): void
    {
        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame('Österreich', $t->get('messages.regional'));
    }

    /**
     * @return void
     */
    public function test_primary_locale_wins_over_fallbacks(): void
    {
        // 'common' exists in all three — de_AT is the primary, but de_AT has no 'common'
        // so 'de' (first fallback) should win over 'en' (second fallback)
        $t = new Translator('de', ['en'], $this->langPath);

        $this->assertSame('Deutsch', $t->get('messages.common'));
    }

    // ─── Chain fallthrough ───────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_falls_back_to_first_chain_locale_when_primary_missing(): void
    {
        // de_AT has no 'common' → falls back to 'de' → 'Deutsch'
        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame('Deutsch', $t->get('messages.common'));
    }

    /**
     * @return void
     */
    public function test_falls_back_to_second_chain_locale_when_first_missing(): void
    {
        // de_AT has no 'fallback', 'de' has no 'fallback' → falls back to 'en'
        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame('English Only', $t->get('messages.fallback'));
    }

    /**
     * @return void
     */
    public function test_falls_back_through_first_when_key_only_in_second(): void
    {
        // de_AT has no 'de_only', 'de' has it → should return 'Nur Deutsch'
        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame('Nur Deutsch', $t->get('messages.de_only'));
    }

    // ─── Key not found in any locale ─────────────────────────────────────────

    /**
     * @return void
     */
    public function test_returns_raw_key_when_missing_in_all_locales(): void
    {
        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame('messages.nonexistent', $t->get('messages.nonexistent'));
    }

    // ─── String fallback still works (backward compat) ───────────────────────

    /**
     * @return void
     */
    public function test_string_fallback_still_works(): void
    {
        $t = new Translator('de_AT', 'en', $this->langPath);

        $this->assertSame('English Only', $t->get('messages.fallback'));
    }

    /**
     * @return void
     */
    public function test_string_fallback_does_not_find_de_only_key(): void
    {
        // With only 'en' as fallback, 'de_only' key is not in 'en'
        $t = new Translator('de_AT', 'en', $this->langPath);

        $this->assertSame('messages.de_only', $t->get('messages.de_only'));
    }

    // ─── getFallbackLocale / getFallbackLocales ───────────────────────────────

    /**
     * @return void
     */
    public function test_get_fallback_locale_returns_first_in_chain(): void
    {
        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame('de', $t->getFallbackLocale());
    }

    /**
     * @return void
     */
    public function test_get_fallback_locales_returns_full_chain(): void
    {
        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame(['de', 'en'], $t->getFallbackLocales());
    }

    /**
     * @return void
     */
    public function test_get_fallback_locale_returns_string_when_constructed_with_string(): void
    {
        $t = new Translator('de_AT', 'en', $this->langPath);

        $this->assertSame('en', $t->getFallbackLocale());
    }

    /**
     * @return void
     */
    public function test_get_fallback_locales_returns_single_element_array_for_string(): void
    {
        $t = new Translator('de_AT', 'en', $this->langPath);

        $this->assertSame(['en'], $t->getFallbackLocales());
    }

    // ─── trans_choice also uses the chain ────────────────────────────────────

    /**
     * @return void
     */
    public function test_trans_choice_uses_fallback_chain(): void
    {
        // de_AT has no 'fallback' → chain falls through to 'en'
        // Add a plural key only in en
        file_put_contents(
            $this->langPath . '/en/counts.php',
            "<?php\nreturn ['items' => 'no items|one item|:count items'];\n",
        );

        $t = new Translator('de_AT', ['de', 'en'], $this->langPath);

        $this->assertSame('3 items', $t->transChoice('counts.items', 3));
    }
}
