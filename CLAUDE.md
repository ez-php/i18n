# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are the one carve-out, since they exist specifically to be extended.

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum` and
`opcache` → `OPCache` are existing exceptions the guess gets wrong).

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks all ~40
  `CLAUDE.md` copies as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| **next free** | **3311** | **6383** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project is the one exception, since it has no host/container split and uses `REDIS_PORT` for both.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/i18n

Locale-based translator — loads PHP array language files and resolves keys with placeholder replacement.

---

## Source Structure

```
src/
├── Translator.php                — Loads lang files, resolves dot-notation keys, replaces :placeholders
├── LocaleFormatter.php           — Locale-aware number/currency/date formatting via ext-intl (NumberFormatter, IntlDateFormatter)
└── TranslatorServiceProvider.php — Reads app.locale, app.fallback_locales (chain, optional) / app.fallback_locale from config; binds Translator

tests/
├── TestCase.php                          — Base PHPUnit test case
├── TranslatorTest.php                    — Covers Translator: key resolution, fallback, placeholders, caching
├── LocaleFormatterTest.php               — Covers LocaleFormatter: number, currency, date, dateTime formatting
└── TranslatorServiceProviderTest.php     — Covers provider registration and config wiring
```

---

## Key Classes and Responsibilities

### Translator (`src/Translator.php`)

Loads PHP array language files from `lang/<locale>/<namespace>.php` and resolves translation keys.

**Construction:**
```php
$t = new Translator('de', 'en', '/path/to/lang');
```

| Parameter | Meaning |
|---|---|
| `$locale` | Active locale (mutable via `setLocale()`) |
| `$fallbackLocale` | Used when key not found in active locale (immutable) |
| `$langPath` | Absolute path to the `lang/` directory |

**Key format:** `<namespace>.<subkey>` where:
- `namespace` maps to a file: `lang/<locale>/<namespace>.php`
- `subkey` supports dot-notation for nested arrays: `"min.string"` → `$data['min']['string']`

**`get(string $key, array $replacements = []): string`**

Resolution order:
1. Active locale → `lang/<locale>/<namespace>.php`
2. Fallback locale → `lang/<fallback>/<namespace>.php`
3. Raw key returned as-is (never throws)

Placeholders are `:name` style (colon prefix). Replacement values may be `string|int|float`.

**Lazy file loading with per-locale/namespace caching** — each `lang/<locale>/<namespace>.php` is loaded once via `require` and cached in `$cache`. Subsequent calls to the same locale+namespace read from the array. Files that do not exist or do not return an array are cached as `[]`.

**`setLocale(string $locale): void`** — changes the active locale at runtime (e.g. per-request based on `Accept-Language`). Does not invalidate the cache.

---

### Language File Format

Each file returns a plain PHP array. Nesting is supported.

```
lang/
├── en/
│   ├── validation.php    — ['required' => 'The :field field is required.', 'min' => ['string' => '...']]
│   └── messages.php      — ['welcome' => 'Welcome, :name!']
└── de/
    ├── validation.php
    └── messages.php
```

```php
// lang/en/validation.php
return [
    'required' => 'The :field field is required.',
    'min' => [
        'string'  => 'The :field field must be at least :min characters.',
        'numeric' => 'The :field field must be at least :min.',
    ],
];
```

---

### TranslatorServiceProvider (`src/TranslatorServiceProvider.php`)

Reads `app.locale` and the fallback locale(s) from `Config` and binds `Translator` lazily.

| Config key | Default | Meaning |
|---|---|---|
| `app.locale` | `'en'` | Active locale at boot time |
| `app.fallback_locales` | — | Optional ordered fallback chain (`list<string>`); takes precedence over `app.fallback_locale` when set to a non-empty array. Not env-backed — set directly in `config/app.php` |
| `app.fallback_locale` | `'en'` | Single fallback locale, used when `app.fallback_locales` is absent |

The `lang/` path is resolved via `$app->basePath('lang')`.

---

## Design Decisions and Constraints

- **PHP array files, not YAML/JSON** — Arrays are parsed by the PHP engine (no parser needed, no extra dependency), cached by OPcache, and type-safe. Switching format would require adding a parser and losing OPcache benefits.
- **Keys without a dot are returned as-is** — A key without a namespace separator cannot map to a file. Returning the raw key instead of throwing keeps rendering code simple (`echo $t->get('some.key')` is always safe).
- **No locale auto-detection** — The active locale is set explicitly at construction (from config) or changed via `setLocale()`. Auto-detection from `Accept-Language` is the application's responsibility (e.g. in middleware).
- **Fallback supports an ordered chain** — `Translator`'s constructor accepts either a single fallback locale or an ordered `list<string>`; `resolveWithChain()` tries the active locale, then each fallback in order, before the raw key is returned. `TranslatorServiceProvider` wires this from the optional `app.fallback_locales` config key (falling back to the single `app.fallback_locale` when absent), so the chain is reachable from application config, not just direct construction.
- **`setLocale()` does not invalidate the cache** — The cache is keyed by `locale/namespace`. Switching locale simply directs future lookups to a different cache bucket. Old buckets stay in memory for the request lifetime — this is acceptable since the number of locale/namespace combinations in a request is small.
- **`Translator` is injected, not a static façade** — Unlike `Auth` and `Event`, the translator has no global state requirement. It should be constructor-injected. Use the container to resolve it.

---

## Testing Approach

- **No external infrastructure required** — Tests write temporary `lang/` directory trees to `sys_get_temp_dir()` and clean up in `tearDown`.
- **Test the fallback chain** — Write a key only in the fallback locale; assert it resolves correctly. Write a key in both; assert the active locale wins.
- **Test placeholder replacement** — Assert `:field`, `:min`, `:max` etc. are substituted correctly in all combinations.
- **Test cache behaviour** — Mutate the underlying file between calls to confirm the cache prevents re-reads (if relevant), or confirm `setLocale()` switches buckets.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Declare indirectly used classes with `#[UsesClass]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Locale auto-detection from `Accept-Language` | Application middleware |
| Locale-specific CLDR plural rules (Polish's 4 forms, Arabic's 6, …) | Application layer or a future extension — `transChoice()` implements only pipe-separated variants selected by `min(max(0, count), variants - 1)`, which fits English-like zero/one/many languages |
| Date/number/currency formatting | PHP `Intl` extension, application layer |
| Translation of validation error messages | `ez-php/validation` (injects `Translator` optionally) |
| Loading translations from a database | Application-level `Translator` subclass or decorator |
| Message compilation / caching to PHP files | Out of scope — OPcache handles PHP array files natively |
