<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\I18n\LocaleFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Class LocaleFormatterTest
 *
 * Tests LocaleFormatter — thin wrappers around NumberFormatter and IntlDateFormatter.
 *
 * @package Tests\I18n
 */
#[CoversClass(LocaleFormatter::class)]
final class LocaleFormatterTest extends TestCase
{
    // ─── number() ────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_number_formats_integer_en_us(): void
    {
        $f = new LocaleFormatter('en_US');

        $this->assertSame('1,234', $f->number(1234));
    }

    /**
     * @return void
     */
    public function test_number_formats_float_en_us(): void
    {
        $f = new LocaleFormatter('en_US');

        $this->assertSame('1,234.5', $f->number(1234.5));
    }

    /**
     * @return void
     */
    public function test_number_formats_integer_de_de(): void
    {
        $f = new LocaleFormatter('de_DE');

        // German uses period as thousands separator, comma as decimal
        $result = $f->number(1234);
        $this->assertStringContainsString('1', $result);
        $this->assertStringContainsString('234', $result);
    }

    /**
     * @return void
     */
    public function test_number_formats_zero(): void
    {
        $f = new LocaleFormatter('en_US');

        $this->assertSame('0', $f->number(0));
    }

    /**
     * @return void
     */
    public function test_number_formats_negative(): void
    {
        $f = new LocaleFormatter('en_US');

        $this->assertSame('-42', $f->number(-42));
    }

    // ─── currency() ──────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_currency_returns_non_empty_string(): void
    {
        $f = new LocaleFormatter('en_US');
        $result = $f->currency(19.99, 'USD');

        $this->assertNotEmpty($result);
    }

    /**
     * @return void
     */
    public function test_currency_contains_amount_digits(): void
    {
        $f = new LocaleFormatter('en_US');
        $result = $f->currency(19.99, 'USD');

        $this->assertStringContainsString('19', $result);
        $this->assertStringContainsString('99', $result);
    }

    /**
     * @return void
     */
    public function test_currency_de_de_eur(): void
    {
        $f = new LocaleFormatter('de_DE');
        $result = $f->currency(9.99, 'EUR');

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('9', $result);
    }

    // ─── date() ──────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_date_returns_string_containing_year(): void
    {
        $f = new LocaleFormatter('en_US');
        $date = new \DateTimeImmutable('2024-06-15');

        $result = $f->date($date);

        $this->assertStringContainsString('2024', $result);
    }

    /**
     * @return void
     */
    public function test_date_de_de_returns_string(): void
    {
        $f = new LocaleFormatter('de_DE');
        $date = new \DateTimeImmutable('2024-06-15');

        $result = $f->date($date);

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('2024', $result);
    }

    /**
     * @return void
     */
    public function test_date_short_style(): void
    {
        $f = new LocaleFormatter('en_US');
        $date = new \DateTimeImmutable('2024-06-15');

        $short = $f->date($date, \IntlDateFormatter::SHORT);
        $medium = $f->date($date, \IntlDateFormatter::MEDIUM);

        // Short is typically shorter than medium
        $this->assertLessThanOrEqual(strlen($medium), strlen($short) + 5);
    }

    // ─── dateTime() ──────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_date_time_returns_string_with_year(): void
    {
        $f = new LocaleFormatter('en_US');
        $date = new \DateTimeImmutable('2024-06-15 14:30:00');

        $result = $f->dateTime($date);

        $this->assertStringContainsString('2024', $result);
    }

    /**
     * @return void
     */
    public function test_date_time_includes_time_component(): void
    {
        $f = new LocaleFormatter('en_US');
        $date = new \DateTimeImmutable('2024-06-15 14:30:00');

        $dateOnly = $f->date($date);
        $dateAndTime = $f->dateTime($date);

        // dateTime string is longer than date-only
        $this->assertGreaterThan(strlen($dateOnly), strlen($dateAndTime));
    }

    // ─── getLocale() ─────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_locale_returns_constructor_locale(): void
    {
        $f = new LocaleFormatter('fr_FR');

        $this->assertSame('fr_FR', $f->getLocale());
    }
}
