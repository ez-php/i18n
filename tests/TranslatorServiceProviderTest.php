<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\I18n\Translator;
use EzPhp\I18n\TranslatorServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
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
    private string $langPath;

    /**
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
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        @unlink($this->langPath . '/en/validation.php');
        @unlink($this->langPath . '/de/validation.php');
        @rmdir($this->langPath . '/en');
        @rmdir($this->langPath . '/de');
        @rmdir($this->langPath);
    }

    /**
     * Build a minimal container stub pre-loaded with a ConfigInterface binding.
     */
    private function makeContainer(string $locale = 'en', string $fallback = 'en', string $langPath = ''): ContainerInterface
    {
        $config = new class ($locale, $fallback, $langPath) implements ConfigInterface {
            public function __construct(
                private readonly string $locale,
                private readonly string $fallback,
                private readonly string $langPath,
            ) {
            }

            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'app.locale' => $this->locale,
                    'app.fallback_locale' => $this->fallback,
                    'app.lang_path' => $this->langPath,
                    default => $default,
                };
            }
        };

        return new class ($config) implements ContainerInterface {
            /** @var array<string, callable> */
            private array $bindings = [];

            /** @var array<string, object> */
            private array $instances = [];

            public function __construct(ConfigInterface $config)
            {
                $this->instances[ConfigInterface::class] = $config;
            }

            public function bind(string $abstract, string|callable|null $factory = null): void
            {
                if (is_callable($factory)) {
                    $this->bindings[$abstract] = $factory;
                }
            }

            public function instance(string $abstract, object $instance): void
            {
                $this->instances[$abstract] = $instance;
            }

            /**
             * @template T of object
             * @param class-string<T> $abstract
             * @return T
             */
            public function make(string $abstract): mixed
            {
                if (isset($this->instances[$abstract])) {
                    /** @var T */
                    return $this->instances[$abstract];
                }

                if (isset($this->bindings[$abstract])) {
                    /** @var T */
                    return $this->instances[$abstract] = ($this->bindings[$abstract])($this);
                }

                throw new \RuntimeException("No binding registered for {$abstract}.");
            }
        };
    }

    /**
     * @return void
     */
    public function test_translator_is_bound_in_container(): void
    {
        $container = $this->makeContainer(langPath: $this->langPath);
        (new TranslatorServiceProvider($container))->register();

        $this->assertInstanceOf(Translator::class, $container->make(Translator::class));
    }

    /**
     * @return void
     */
    public function test_translator_uses_locale_from_config(): void
    {
        $container = $this->makeContainer(locale: 'de', langPath: $this->langPath);
        (new TranslatorServiceProvider($container))->register();

        $this->assertSame('de', $container->make(Translator::class)->getLocale());
    }

    /**
     * @return void
     */
    public function test_translator_uses_fallback_locale_from_config(): void
    {
        $container = $this->makeContainer(locale: 'de', fallback: 'en', langPath: $this->langPath);
        (new TranslatorServiceProvider($container))->register();

        $this->assertSame('en', $container->make(Translator::class)->getFallbackLocale());
    }

    /**
     * @return void
     */
    public function test_translator_resolves_english_validation_messages(): void
    {
        $container = $this->makeContainer(locale: 'en', langPath: $this->langPath);
        (new TranslatorServiceProvider($container))->register();

        $message = $container->make(Translator::class)->get('validation.required', ['field' => 'email']);

        $this->assertStringContainsString('email', $message);
        $this->assertStringNotContainsString(':field', $message);
    }

    /**
     * @return void
     */
    public function test_translator_resolves_german_validation_messages(): void
    {
        $container = $this->makeContainer(locale: 'de', fallback: 'en', langPath: $this->langPath);
        (new TranslatorServiceProvider($container))->register();

        $message = $container->make(Translator::class)->get('validation.required', ['field' => 'E-Mail']);

        $this->assertStringContainsString('E-Mail', $message);
        $this->assertStringContainsString('erforderlich', $message);
    }
}
