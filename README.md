# ezphp/i18n

Internationalisation module for the [ez-php framework](https://github.com/ezphp/framework) — file-based translations with dot-notation keys and locale fallback.

[![CI](https://github.com/ezphp/i18n/actions/workflows/ci.yml/badge.svg)](https://github.com/ezphp/i18n/actions/workflows/ci.yml)

## Requirements

- PHP 8.5+
- ezphp/framework ^1.0

## Installation

```bash
composer require ezphp/i18n
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

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
