<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\Application\Application;
use EzPhp\I18n\Translator;
use EzPhp\I18n\TranslatorServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class TranslatorServiceProviderTest
 *
 * @package Tests\I18n
 */
#[CoversClass(TranslatorServiceProvider::class)]
#[UsesClass(Translator::class)]
final class TranslatorServiceProviderTest extends ApplicationTestCase
{
    private string $langPath;

    /**
     * Lang files and env vars are set before parent::setUp() so that Config
     * picks them up lazily on the first make(Translator::class) call.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->langPath = sys_get_temp_dir() . '/ez-i18n-provider-' . uniqid();

        mkdir($this->langPath . '/en', 0o755, true);
        mkdir($this->langPath . '/de', 0o755, true);

        file_put_contents(
            $this->langPath . '/en/validation.php',
            "<?php\nreturn ['required' => 'The :field field is required.'];\n",
        );

        file_put_contents(
            $this->langPath . '/de/validation.php',
            "<?php\nreturn ['required' => 'Das Feld :field ist erforderlich.'];\n",
        );

        putenv('APP_LOCALE=en');
        putenv('APP_FALLBACK_LOCALE=en');
        putenv('APP_LANG_PATH=' . $this->langPath);

        parent::setUp();
    }

    /**
     * @param Application $app
     *
     * @return void
     */
    protected function configureApplication(Application $app): void
    {
        $app->register(TranslatorServiceProvider::class);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('APP_LOCALE');
        putenv('APP_FALLBACK_LOCALE');
        putenv('APP_LANG_PATH');

        @unlink($this->langPath . '/en/validation.php');
        @unlink($this->langPath . '/de/validation.php');
        @rmdir($this->langPath . '/en');
        @rmdir($this->langPath . '/de');
        @rmdir($this->langPath);

        parent::tearDown();
    }

    /**
     * @return void
     */
    public function test_translator_is_bound_in_container(): void
    {
        $this->assertInstanceOf(Translator::class, $this->app()->make(Translator::class));
    }

    /**
     * @return void
     */
    public function test_translator_uses_locale_from_config(): void
    {
        putenv('APP_LOCALE=de');

        $this->assertSame('de', $this->app()->make(Translator::class)->getLocale());
    }

    /**
     * @return void
     */
    public function test_translator_uses_fallback_locale_from_config(): void
    {
        putenv('APP_LOCALE=de');
        putenv('APP_FALLBACK_LOCALE=en');

        $this->assertSame('en', $this->app()->make(Translator::class)->getFallbackLocale());
    }

    /**
     * @return void
     */
    public function test_translator_resolves_english_validation_messages(): void
    {
        $message = $this->app()->make(Translator::class)->get('validation.required', ['field' => 'email']);

        $this->assertStringContainsString('email', $message);
        $this->assertStringNotContainsString(':field', $message);
    }

    /**
     * @return void
     */
    public function test_translator_resolves_german_validation_messages(): void
    {
        putenv('APP_LOCALE=de');
        putenv('APP_FALLBACK_LOCALE=en');

        $message = $this->app()->make(Translator::class)->get('validation.required', ['field' => 'E-Mail']);

        $this->assertStringContainsString('E-Mail', $message);
        $this->assertStringContainsString('erforderlich', $message);
    }
}
