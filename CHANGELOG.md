# Changelog

All notable changes to `ez-php/i18n` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Changed
- Updated `ez-php/contracts` dependency constraint to `^1.2`

---

## [v1.0.1] — 2026-03-25

### Changed
- Tightened all `ez-php/*` dependency constraints from `"*"` to `"^1.0"` for predictable resolution

---

## [v1.0.0] — 2026-03-24

### Added
- `Translator` — implements `TranslatorInterface`; loads PHP array translation files from `lang/<locale>/` directories
- Dot-notation key resolution — `"validation.required"` maps to `$translations['validation']['required']`
- Named placeholder replacement — `":attribute is required"` with `['attribute' => 'email']` produces `"email is required"`
- Locale fallback — falls back to the default locale when a key is missing in the active locale
- `I18nServiceProvider` — resolves locale from config, instantiates and binds the `Translator`
- `TranslationException` for missing translation files and malformed key paths
