# ez-php/i18n

Internationalisation module for the [ez-php framework](https://github.com/ez-php/framework) — file-based translations with dot-notation keys and locale fallback.

[![CI](https://github.com/ez-php/i18n/actions/workflows/ci.yml/badge.svg)](https://github.com/ez-php/i18n/actions/workflows/ci.yml)

## Requirements

- PHP 8.5+
- ez-php/framework 0.*

## Installation

```bash
composer require ez-php/i18n
```

## Setup

Register the service provider:

```php
$app->register(\EzPhp\I18n\TranslatorServiceProvider::class);
```

Add translation files under `lang/{locale}/`:

```
lang/
  en/
    messages.php   → ['welcome' => 'Welcome, :name!']
  de/
    messages.php   → ['welcome' => 'Willkommen, :name!']
```

## Usage

```php
$translator = $app->make(\EzPhp\I18n\Translator::class);

echo $translator->get('messages.welcome', ['name' => 'Alice']);
// Welcome, Alice!

$translator->setLocale('de');
echo $translator->get('messages.welcome', ['name' => 'Alice']);
// Willkommen, Alice!
```

### Plurals

`transChoice()` accepts the simple pipe form (`'no apples|one apple|:count apples'`, chosen by
position) or an ICU plural message, which uses the full CLDR rules of the locale when `ext-intl`
is installed:

```php
// lang/pl/messages.php
'apples' => '{count, plural, one {# jabłko} few {# jabłka} many {# jabłek} other {# jabłka}}',

$translator->transChoice('messages.apples', 22); // "22 jabłka"
$translator->transChoice('messages.apples', 25); // "25 jabłek"
```

`=0 {…}` matches an exact count, `#` is the count, and `:placeholders` still work. Without `ext-intl`
only `=N`, `one` and `other` are used.

## Locale formatting

`LocaleFormatter` wraps PHP's `ext-intl` extension to format numbers, currencies, and dates in a locale-aware way:

```php
use EzPhp\I18n\LocaleFormatter;

$fmt = new LocaleFormatter('de_DE');

echo $fmt->number(1234567.89);         // '1.234.567,89'
echo $fmt->currency(9.99, 'EUR');      // '9,99 €'
echo $fmt->date(new \DateTimeImmutable('2024-06-15')); // '15.06.2024'
echo $fmt->dateTime(new \DateTimeImmutable('2024-06-15 14:30:00')); // '15.06.2024, 14:30:00'
```

Requires PHP's `ext-intl` extension.

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
