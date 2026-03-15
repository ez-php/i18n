<?php

declare(strict_types=1);

namespace EzPhp\I18n;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;

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
        $this->app->bind(Translator::class, function (ContainerInterface $app): Translator {
            $config = $app->make(ConfigInterface::class);
            $localeRaw = $config->get('app.locale', 'en');
            $fallbackRaw = $config->get('app.fallback_locale', 'en');
            $locale = is_string($localeRaw) ? $localeRaw : 'en';
            $fallback = is_string($fallbackRaw) ? $fallbackRaw : 'en';
            $langPathRaw = $config->get('app.lang_path', '');
            $langPath = is_string($langPathRaw) ? $langPathRaw : '';

            return new Translator($locale, $fallback, $langPath);
        });
    }
}
