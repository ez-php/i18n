<?php

declare(strict_types=1);

namespace EzPhp\I18n;

/**
 * Class LocaleFormatter
 *
 * Thin wrappers around PHP's NumberFormatter and IntlDateFormatter, bound to a
 * specific locale. Provides locale-aware number, currency, and date formatting
 * without adding any external dependencies (uses the ext-intl extension).
 *
 * Usage:
 *   $fmt = new LocaleFormatter('de_DE');
 *   $fmt->number(1234.5);            // '1.234,5'
 *   $fmt->currency(9.99, 'EUR');     // '9,99 €'
 *   $fmt->date(new \DateTimeImmutable('2024-06-15')); // '15.06.2024'
 *
 * @package EzPhp\I18n
 */
final class LocaleFormatter
{
    /**
     * LocaleFormatter Constructor
     *
     * @param string $locale BCP 47 locale tag, e.g. 'en_US', 'de_DE', 'fr_FR'.
     */
    public function __construct(private readonly string $locale)
    {
    }

    /**
     * Format a number using the active locale.
     *
     * @param int|float $number The value to format.
     * @param int       $style  NumberFormatter style constant (default: DECIMAL).
     *
     * @return string
     *
     * @throws \RuntimeException If formatting fails.
     */
    public function number(int|float $number, int $style = \NumberFormatter::DECIMAL): string
    {
        $formatter = new \NumberFormatter($this->locale, $style);
        $result = $formatter->format($number);

        if (!is_string($result)) {
            throw new \RuntimeException(
                "NumberFormatter failed for locale '{$this->locale}': " . $formatter->getErrorMessage(),
            );
        }

        return $result;
    }

    /**
     * Format a monetary amount in the given currency using the active locale.
     *
     * @param float  $amount   The monetary value.
     * @param string $currency ISO 4217 currency code, e.g. 'USD', 'EUR'.
     *
     * @return string
     *
     * @throws \RuntimeException If formatting fails.
     */
    public function currency(float $amount, string $currency): string
    {
        $formatter = new \NumberFormatter($this->locale, \NumberFormatter::CURRENCY);
        $result = $formatter->formatCurrency($amount, $currency);

        if ($result === false) {
            throw new \RuntimeException(
                "NumberFormatter::formatCurrency failed for locale '{$this->locale}': " . $formatter->getErrorMessage(),
            );
        }

        return $result;
    }

    /**
     * Format a date (date only, no time component) using the active locale.
     *
     * @param \DateTimeInterface $date     The date to format.
     * @param int                $dateType IntlDateFormatter date style constant (default: MEDIUM).
     *
     * @return string
     *
     * @throws \RuntimeException If formatting fails.
     */
    public function date(\DateTimeInterface $date, int $dateType = \IntlDateFormatter::MEDIUM): string
    {
        $formatter = new \IntlDateFormatter(
            $this->locale,
            $dateType,
            \IntlDateFormatter::NONE,
        );

        $result = $formatter->format($date);

        if ($result === false) {
            throw new \RuntimeException(
                "IntlDateFormatter failed for locale '{$this->locale}': " . $formatter->getErrorMessage(),
            );
        }

        return $result;
    }

    /**
     * Format a date and time using the active locale.
     *
     * @param \DateTimeInterface $date     The date/time to format.
     * @param int                $dateType IntlDateFormatter date style constant (default: MEDIUM).
     * @param int                $timeType IntlDateFormatter time style constant (default: SHORT).
     *
     * @return string
     *
     * @throws \RuntimeException If formatting fails.
     */
    public function dateTime(
        \DateTimeInterface $date,
        int $dateType = \IntlDateFormatter::MEDIUM,
        int $timeType = \IntlDateFormatter::SHORT,
    ): string {
        $formatter = new \IntlDateFormatter(
            $this->locale,
            $dateType,
            $timeType,
        );

        $result = $formatter->format($date);

        if ($result === false) {
            throw new \RuntimeException(
                "IntlDateFormatter failed for locale '{$this->locale}': " . $formatter->getErrorMessage(),
            );
        }

        return $result;
    }

    /**
     * Returns the locale this formatter is bound to.
     *
     * @return string
     */
    public function getLocale(): string
    {
        return $this->locale;
    }
}
