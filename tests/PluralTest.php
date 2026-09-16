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
        mkdir($this->langPath . '/ru', 0o755, true);

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

        file_put_contents($this->langPath . '/ru/messages.php', <<<'PHP'
            <?php
            return [
                'apples' => ':count яблоко|:count яблока|:count яблок',
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

    // ─── Slavic one/few/many (ru) ───────────────────────────────────────────

    /**
     * n%10==1 && n%100!=11 → "one" variant.
     *
     * @return void
     */
    public function test_slavic_locale_selects_one_variant_for_1_21_101(): void
    {
        $t = new Translator('ru', 'en', $this->langPath);

        $this->assertSame('1 яблоко', $t->transChoice('messages.apples', 1));
        $this->assertSame('21 яблоко', $t->transChoice('messages.apples', 21));
        $this->assertSame('101 яблоко', $t->transChoice('messages.apples', 101));
    }

    /**
     * n%10 in 2..4 && n%100 not in 12..14 → "few" variant.
     *
     * @return void
     */
    public function test_slavic_locale_selects_few_variant_for_2_3_4_22(): void
    {
        $t = new Translator('ru', 'en', $this->langPath);

        $this->assertSame('2 яблока', $t->transChoice('messages.apples', 2));
        $this->assertSame('3 яблока', $t->transChoice('messages.apples', 3));
        $this->assertSame('4 яблока', $t->transChoice('messages.apples', 4));
        $this->assertSame('22 яблока', $t->transChoice('messages.apples', 22));
    }

    /**
     * Everything else (0, 5-20, 11-14, 25, ...) → "many" variant.
     *
     * @return void
     */
    public function test_slavic_locale_selects_many_variant_for_0_5_11_25(): void
    {
        $t = new Translator('ru', 'en', $this->langPath);

        $this->assertSame('0 яблок', $t->transChoice('messages.apples', 0));
        $this->assertSame('5 яблок', $t->transChoice('messages.apples', 5));
        $this->assertSame('11 яблок', $t->transChoice('messages.apples', 11));
        $this->assertSame('12 яблок', $t->transChoice('messages.apples', 12));
        $this->assertSame('25 яблок', $t->transChoice('messages.apples', 25));
    }

    /**
     * The Slavic rule only kicks in for a 3-variant message when the active
     * locale is Slavic — an English (positional) message stays positional.
     *
     * @return void
     */
    public function test_non_slavic_locale_keeps_positional_selection(): void
    {
        $t = new Translator('en', 'en', $this->langPath);

        // count=2 would be "few" under the Slavic rule, but 'en' keeps
        // the existing positional formula: min(max(0, 2), 2) = index 2.
        $this->assertSame('2 apples', $t->transChoice('messages.apples', 2));
    }
}
