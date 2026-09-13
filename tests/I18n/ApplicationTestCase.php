<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\Testing\ApplicationTestCase as EzPhpApplicationTestCase;
use RuntimeException;

/**
 * Base class for i18n module tests that need a bootstrapped Application.
 *
 * Creates a temporary application root containing a config/app.php file
 * that reads locale settings from env vars at require-time. Because Config
 * and all service bindings are resolved lazily, env vars set before the first
 * make() call in a test method are picked up correctly.
 *
 * Set APP_LOCALE, APP_FALLBACK_LOCALE, APP_LANG_PATH, and (optionally) a
 * comma-separated APP_FALLBACK_LOCALES via putenv() in setUp() before calling
 * parent::setUp() to configure per-test locale. APP_FALLBACK_LOCALES is a
 * test-only convenience — in a real application, `app.fallback_locales` is a
 * PHP array set directly in config/app.php, since it is not env-backed there.
 *
 * @package Tests\I18n
 */
abstract class ApplicationTestCase extends EzPhpApplicationTestCase
{
    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-i18n-test-' . uniqid('', true);
        $configDir = $path . DIRECTORY_SEPARATOR . 'config';

        mkdir($configDir, 0o777, true);

        $content = <<<'PHP'
            <?php

            declare(strict_types=1);

            $fallbackLocales = getenv('APP_FALLBACK_LOCALES');

            return [
                'locale'           => getenv('APP_LOCALE') ?: 'en',
                'fallback_locale'  => getenv('APP_FALLBACK_LOCALE') ?: 'en',
                'fallback_locales' => $fallbackLocales !== false && $fallbackLocales !== ''
                    ? explode(',', $fallbackLocales)
                    : [],
                'lang_path'        => getenv('APP_LANG_PATH') ?: '',
            ];
            PHP;

        $result = file_put_contents($configDir . DIRECTORY_SEPARATOR . 'app.php', $content);

        if ($result === false) {
            throw new RuntimeException('Failed to write app.php for test at ' . $configDir);
        }

        return $path;
    }
}
