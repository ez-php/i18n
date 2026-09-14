<?php

declare(strict_types=1);

namespace EzPhp\I18n;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Contracts\TranslatorInterface;

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
            $locale = is_string($localeRaw) ? $localeRaw : 'en';
            $langPathRaw = $config->get('app.lang_path', '');
            $langPath = is_string($langPathRaw) ? $langPathRaw : '';

            return new Translator($locale, self::resolveFallbackLocales($config), $langPath);
        });

        $this->app->bind(
            TranslatorInterface::class,
            fn (ContainerInterface $app): Translator => $app->make(Translator::class),
        );

        $this->app->bind(LocaleFormatter::class, function (ContainerInterface $app): LocaleFormatter {
            $config = $app->make(ConfigInterface::class);
            $localeRaw = $config->get('app.locale', 'en');
            $locale = is_string($localeRaw) ? $localeRaw : 'en';

            return new LocaleFormatter($locale);
        });
    }

    /**
     * Resolve the fallback locale chain from config.
     *
     * `app.fallback_locales` (plural, list<string>) takes precedence when set,
     * making Translator's multi-level fallback chain reachable from config.
     * Falls back to the single `app.fallback_locale` key (default 'en') for
     * backward compatibility with applications that only configure one.
     *
     * @param ConfigInterface $config
     *
     * @return string|list<string>
     */
    private static function resolveFallbackLocales(ConfigInterface $config): string|array
    {
        $chainRaw = $config->get('app.fallback_locales', null);

        if (is_array($chainRaw) && $chainRaw !== []) {
            $chain = array_values(array_filter($chainRaw, 'is_string'));

            if ($chain !== []) {
                /** @var list<string> $chain */
                return $chain;
            }
        }

        $fallbackRaw = $config->get('app.fallback_locale', 'en');

        return is_string($fallbackRaw) ? $fallbackRaw : 'en';
    }
}
