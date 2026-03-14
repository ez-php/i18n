<?php

declare(strict_types=1);

namespace EzPhp\I18n;

use EzPhp\Application\Application;
use EzPhp\Config\Config;
use EzPhp\ServiceProvider\ServiceProvider;

/**
 * Class TranslatorServiceProvider
 *
 * @package EzPhp\I18n
 */
final class TranslatorServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Translator::class, function (Application $app): Translator {
            $config = $app->make(Config::class);
            $localeRaw = $config->get('app.locale', 'en');
            $fallbackRaw = $config->get('app.fallback_locale', 'en');
            $locale = is_string($localeRaw) ? $localeRaw : 'en';
            $fallback = is_string($fallbackRaw) ? $fallbackRaw : 'en';
            $langPath = $app->basePath('lang');

            return new Translator($locale, $fallback, $langPath);
        });
    }
}
