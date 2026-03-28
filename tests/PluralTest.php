<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\I18n\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class PluralTest
 *
 * Tests Translator::transChoice() — pipe-separated plural variant selection.
 *
 * @package Tests\I18n
 */
#[CoversClass(Translator::class)]
final class PluralTest extends TestCase
{
    private string $langPath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->langPath = sys_get_temp_dir() . '/ez-i18n-plural-' . uniqid();

        mkdir($this->langPath . '/en', 0o755, true);

        file_put_contents($this->langPath . '/en/messages.php', <<<'PHP'
            <?php
            return [
                'apples' => 'no apples|one apple|:count apples',
                'items'  => 'no items|:count item|:count items',
                'binary' => 'zero|many',
                'single' => 'always this',
                'label'  => ':count :label|:count :labels',
            ];
            PHP);
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

    // ─── Zero variant ────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_trans_choice_selects_zero_variant(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('no apples', $t->transChoice('messages.apples', 0));
    }

    // ─── One variant ─────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_trans_choice_selects_one_variant(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('one apple', $t->transChoice('messages.apples', 1));
    }

    // ─── Many variant ────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_trans_choice_selects_many_variant_and_replaces_count(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('5 apples', $t->transChoice('messages.apples', 5));
    }

    /**
     * @return void
     */
    public function test_trans_choice_replaces_count_in_single_item_variant(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('1 item', $t->transChoice('messages.items', 1));
    }

    /**
     * @return void
     */
    public function test_trans_choice_replaces_count_in_many_items_variant(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('3 items', $t->transChoice('messages.items', 3));
    }

    // ─── Binary (2-variant) strings ──────────────────────────────────────────

    /**
     * @return void
     */
    public function test_binary_variants_zero(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('zero', $t->transChoice('messages.binary', 0));
    }

    /**
     * @return void
     */
    public function test_binary_variants_one_uses_last_segment(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('many', $t->transChoice('messages.binary', 1));
    }

    /**
     * @return void
     */
    public function test_binary_variants_many_uses_last_segment(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('many', $t->transChoice('messages.binary', 99));
    }

    // ─── Single variant (no pipe) ─────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_single_variant_returned_for_any_count(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('always this', $t->transChoice('messages.single', 0));
        $this->assertSame('always this', $t->transChoice('messages.single', 5));
    }

    // ─── Additional replacements ──────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_additional_replacements_are_applied(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        // 'label' key: '0 :label|:count :labels'
        // count=1 → index 1 → ':count :labels'... wait, index min(1, 1) = 1 → ':count :labels'
        // Hmm, the 'label' value is ':count :label|:count :labels'
        // count=0 → index 0 → ':count :label' → '0 item' (with label=item)
        $result = $t->transChoice('messages.label', 0, ['label' => 'item', 'labels' => 'items']);

        $this->assertSame('0 item', $result);
    }

    /**
     * @return void
     */
    public function test_additional_replacements_many_variant(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        // count=3 → index min(3, 1) = 1 → ':count :labels' → '3 items'
        $result = $t->transChoice('messages.label', 3, ['label' => 'item', 'labels' => 'items']);

        $this->assertSame('3 items', $result);
    }

    // ─── Missing key ─────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_returns_key_when_translation_missing(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('messages.nonexistent', $t->transChoice('messages.nonexistent', 5));
    }

    /**
     * @return void
     */
    public function test_returns_key_without_dot(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        $this->assertSame('nodot', $t->transChoice('nodot', 1));
    }
}
