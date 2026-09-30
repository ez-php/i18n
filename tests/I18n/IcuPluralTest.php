<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\I18n\IcuPluralFallback;
use EzPhp\I18n\Translator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * ICU plural messages in transChoice(): full CLDR rules through ext-intl, and the
 * ext-intl-free fallback.
 *
 * @package Tests\I18n
 */
#[CoversClass(Translator::class)]
#[CoversClass(IcuPluralFallback::class)]
final class IcuPluralTest extends TestCase
{
    private string $langPath;

    protected function setUp(): void
    {
        $this->langPath = sys_get_temp_dir() . '/ez-i18n-icu-' . uniqid();

        foreach (['pl' => [
            'apples' => '{count, plural, one {# jabłko} few {# jabłka} many {# jabłek} other {# jabłka}}',
            'cart' => '{n, plural, =0 {Koszyk :owner jest pusty} one {:owner ma # produkt} other {:owner ma # produktów}}',
            'legacy' => 'zero|one|many',
        ], 'ar' => [
            'books' => '{count, plural, zero {لا كتب} one {كتاب} two {كتابان} few {# كتب} many {# كتابًا} other {# كتاب}}',
        ]] as $locale => $messages) {
            mkdir($this->langPath . '/' . $locale, 0o755, true);
            file_put_contents($this->langPath . '/' . $locale . '/messages.php', '<?php return ' . var_export($messages, true) . ';');
        }
    }

    protected function tearDown(): void
    {
        foreach (glob($this->langPath . '/*/messages.php') ?: [] as $file) {
            unlink($file);
            rmdir(dirname($file));
        }

        rmdir($this->langPath);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function polish(): array
    {
        return [
            '1 one' => [1, '1 jabłko'],
            '2 few' => [2, '2 jabłka'],
            '4 few' => [4, '4 jabłka'],
            '5 many' => [5, '5 jabłek'],
            '12 many' => [12, '12 jabłek'],
            '22 few' => [22, '22 jabłka'],
            '25 many' => [25, '25 jabłek'],
            '0 many' => [0, '0 jabłek'],
        ];
    }

    #[DataProvider('polish')]
    public function test_polish_uses_all_cldr_forms(int $count, string $expected): void
    {
        self::assertSame($expected, (new Translator('pl', 'pl', $this->langPath))->transChoice('messages.apples', $count));
    }

    public function test_arabic_six_forms(): void
    {
        $t = new Translator('ar', 'ar', $this->langPath);

        self::assertSame('لا كتب', $t->transChoice('messages.books', 0));
        self::assertSame('كتاب', $t->transChoice('messages.books', 1));
        self::assertSame('كتابان', $t->transChoice('messages.books', 2));
        self::assertSame('3 كتب', $t->transChoice('messages.books', 3));
        self::assertSame('11 كتابًا', $t->transChoice('messages.books', 11));
        self::assertSame('100 كتاب', $t->transChoice('messages.books', 100));
    }

    public function test_exact_matches_any_variable_name_and_colon_replacements(): void
    {
        $t = new Translator('pl', 'pl', $this->langPath);

        self::assertSame('Koszyk Ani jest pusty', $t->transChoice('messages.cart', 0, ['owner' => 'Ani']));
        self::assertSame('Ania ma 1 produkt', $t->transChoice('messages.cart', 1, ['owner' => 'Ania']));
        self::assertSame('Ania ma 7 produktów', $t->transChoice('messages.cart', 7, ['owner' => 'Ania']));
    }

    public function test_pipe_messages_keep_the_positional_formula(): void
    {
        self::assertSame('many', (new Translator('pl', 'pl', $this->langPath))->transChoice('messages.legacy', 5));
    }

    public function test_fallback_picks_exact_then_one_then_other(): void
    {
        $message = '{count, plural, =0 {none} one {# item} few {# items (few)} other {# items}}';

        self::assertSame('none', IcuPluralFallback::format($message, 0));
        self::assertSame('1 item', IcuPluralFallback::format($message, 1));
        self::assertSame('5 items', IcuPluralFallback::format($message, 5));
    }

    public function test_fallback_handles_nested_braces_and_bad_input(): void
    {
        self::assertSame('3 {x} left', IcuPluralFallback::format('{n, plural, other {# {x} left}}', 3));
        self::assertNull(IcuPluralFallback::format('{n, plural, one {unterminated', 1));
        self::assertNull(IcuPluralFallback::format('plain text', 1));
    }
}
