<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\Application\Application;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\I18n\Translator;
use EzPhp\I18n\TranslatorServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionException;
use Tests\TestCase;

/**
 * Class TranslatorServiceProviderTest
 *
 * @package Tests\I18n
 */
#[CoversClass(TranslatorServiceProvider::class)]
#[UsesClass(Translator::class)]
final class TranslatorServiceProviderTest extends TestCase
{
    /**
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_translator_is_bound_in_container(): void
    {
        $app = new Application();
        $app->register(TranslatorServiceProvider::class);
        $app->bootstrap();

        $translator = $app->make(Translator::class);

        $this->assertInstanceOf(Translator::class, $translator);
    }

    /**
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_translator_uses_locale_from_config(): void
    {
        $app = new Application();
        $app->register(TranslatorServiceProvider::class);
        $app->bootstrap();

        $translator = $app->make(Translator::class);

        // Config reads APP_LOCALE from env; default is 'en'
        $this->assertNotEmpty($translator->getLocale());
    }

    /**
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_translator_uses_fallback_locale_from_config(): void
    {
        $app = new Application();
        $app->register(TranslatorServiceProvider::class);
        $app->bootstrap();

        $translator = $app->make(Translator::class);

        $this->assertNotEmpty($translator->getFallbackLocale());
    }

    /**
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_translator_resolves_english_validation_messages(): void
    {
        $app = new Application();
        $app->register(TranslatorServiceProvider::class);
        $app->bootstrap();

        $translator = $app->make(Translator::class);
        $translator->setLocale('en');

        $message = $translator->get('validation.required', ['field' => 'email']);

        $this->assertStringContainsString('email', $message);
        $this->assertStringNotContainsString(':field', $message);
    }

    /**
     * @throws ReflectionException
     * @throws ApplicationException
     * @throws ContainerException
     */
    public function test_translator_resolves_german_validation_messages(): void
    {
        $app = new Application();
        $app->register(TranslatorServiceProvider::class);
        $app->bootstrap();

        $translator = $app->make(Translator::class);
        $translator->setLocale('de');

        $message = $translator->get('validation.required', ['field' => 'E-Mail']);

        $this->assertStringContainsString('E-Mail', $message);
        $this->assertStringContainsString('erforderlich', $message);
    }
}
