<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\I18n\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class TranslatorTest
 *
 * @package Tests\I18n
 */
#[CoversClass(Translator::class)]
final class TranslatorTest extends TestCase
{
    private string $langPath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->langPath = sys_get_temp_dir() . '/ez-i18n-' . uniqid();

        mkdir($this->langPath . '/en', 0o755, true);
        mkdir($this->langPath . '/de', 0o755, true);
        mkdir($this->langPath . '/fr', 0o755, true);

        file_put_contents($this->langPath . '/en/messages.php', <<<'PHP'
            <?php
            return [
                'hello'   => 'Hello :name!',
                'goodbye' => 'Goodbye.',
                'nested'  => [
                    'deep' => 'Nested value',
                ],
            ];
            PHP);

        file_put_contents($this->langPath . '/de/messages.php', <<<'PHP'
            <?php
            return [
                'hello' => 'Hallo :name!',
            ];
            PHP);

        // Intentionally broken file (non-array return)
        file_put_contents($this->langPath . '/en/broken.php', '<?php return "not an array";');
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

    // ─── Basic resolution ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_resolves_known_key(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('Goodbye.', $t->get('messages.goodbye'));
    }

    /**
     * @return void
     */
    public function test_get_replaces_placeholders(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('Hello World!', $t->get('messages.hello', ['name' => 'World']));
    }

    /**
     * @return void
     */
    public function test_get_replaces_numeric_placeholder(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('Hello 42!', $t->get('messages.hello', ['name' => 42]));
    }

    // ─── Locale switching ─────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_uses_active_locale(): void
    {
        $t = new Translator('de', 'en', $this->langPath);

        $this->assertSame('Hallo Welt!', $t->get('messages.hello', ['name' => 'Welt']));
    }

    /**
     * @return void
     */
    public function test_get_falls_back_to_fallback_locale_when_key_missing(): void
    {
        // 'de' has no 'goodbye' key → falls back to 'en'
        $t = new Translator('de', 'en', $this->langPath);

        $this->assertSame('Goodbye.', $t->get('messages.goodbye'));
    }

    /**
     * @return void
     */
    public function test_get_returns_key_when_missing_in_both_locales(): void
    {
        $t = new Translator('de', 'en', $this->langPath);

        $this->assertSame('messages.nonexistent', $t->get('messages.nonexistent'));
    }

    /**
     * @return void
     */
    public function test_get_returns_key_when_missing_namespace(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('nonexistent.key', $t->get('nonexistent.key'));
    }

    /**
     * @return void
     */
    public function test_get_returns_key_without_dot(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('nodot', $t->get('nodot'));
    }

    // ─── Nested keys ─────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_resolves_nested_key(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('Nested value', $t->get('messages.nested.deep'));
    }

    /**
     * @return void
     */
    public function test_get_returns_key_for_non_string_nested_value(): void
    {
        // 'messages.nested' resolves to an array, not a string → returns key
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('messages.nested', $t->get('messages.nested'));
    }

    // ─── Locale without lang file ─────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_falls_back_when_locale_file_missing(): void
    {
        // 'fr' directory exists but has no messages.php → falls back to 'en'
        $t = new Translator('fr', 'en', $this->langPath);

        $this->assertSame('Goodbye.', $t->get('messages.goodbye'));
    }

    /**
     * @return void
     */
    public function test_get_returns_key_when_no_file_in_either_locale(): void
    {
        $t = new Translator('fr', 'fr', $this->langPath);

        $this->assertSame('messages.hello', $t->get('messages.hello'));
    }

    // ─── Broken lang file ────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_returns_key_when_lang_file_returns_non_array(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('broken.key', $t->get('broken.key'));
    }

    // ─── Caching ─────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_file_is_loaded_once_and_cached(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        // Call twice — the file is only loaded once (no error on second call)
        $first = $t->get('messages.goodbye');
        $second = $t->get('messages.goodbye');

        $this->assertSame($first, $second);
    }

    // ─── setLocale / getLocale / getFallbackLocale ───────────────────────────

    /**
     * @return void
     */
    public function test_get_locale_returns_active_locale(): void
    {
        $t = new Translator('en', 'de', $this->langPath);

        $this->assertSame('en', $t->getLocale());
    }

    /**
     * @return void
     */
    public function test_set_locale_changes_active_locale(): void
    {
        $t = new Translator('en', 'en', $this->langPath);
        $t->setLocale('de');

        $this->assertSame('de', $t->getLocale());
        $this->assertSame('Hallo Max!', $t->get('messages.hello', ['name' => 'Max']));
    }

    /**
     * @return void
     */
    public function test_get_fallback_locale_returns_fallback(): void
    {
        $t = new Translator('de', 'en', $this->langPath);

        $this->assertSame('en', $t->getFallbackLocale());
    }
}
