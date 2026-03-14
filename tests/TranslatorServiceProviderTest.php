<?php

declare(strict_types=1);

namespace Tests\I18n;

use EzPhp\Application\Application;
use EzPhp\Application\CoreServiceProviders;
use EzPhp\Config\Config;
use EzPhp\Config\ConfigLoader;
use EzPhp\Config\ConfigServiceProvider;
use EzPhp\Console\Command\MakeControllerCommand;
use EzPhp\Console\Command\MakeMiddlewareCommand;
use EzPhp\Console\Command\MakeMigrationCommand;
use EzPhp\Console\Command\MakeProviderCommand;
use EzPhp\Console\Command\MigrateCommand;
use EzPhp\Console\Command\MigrateRollbackCommand;
use EzPhp\Console\Console;
use EzPhp\Console\ConsoleServiceProvider;
use EzPhp\Console\Input;
use EzPhp\Console\Output;
use EzPhp\Container\Container;
use EzPhp\Database\Database;
use EzPhp\Database\DatabaseServiceProvider;
use EzPhp\Exceptions\ApplicationException;
use EzPhp\Exceptions\ContainerException;
use EzPhp\Exceptions\DefaultExceptionHandler;
use EzPhp\Exceptions\ExceptionHandlerServiceProvider;
use EzPhp\I18n\Translator;
use EzPhp\I18n\TranslatorServiceProvider;
use EzPhp\Migration\MigrationServiceProvider;
use EzPhp\Migration\Migrator;
use EzPhp\Routing\Route;
use EzPhp\Routing\Router;
use EzPhp\Routing\RouterServiceProvider;
use EzPhp\ServiceProvider\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ReflectionException;
use Tests\DatabaseTestCase;

/**
 * Class TranslatorServiceProviderTest
 *
 * @package Tests\I18n
 */
#[CoversClass(TranslatorServiceProvider::class)]
#[UsesClass(Translator::class)]
#[UsesClass(Application::class)]
#[UsesClass(Container::class)]
#[UsesClass(CoreServiceProviders::class)]
#[UsesClass(Config::class)]
#[UsesClass(ConfigLoader::class)]
#[UsesClass(ConfigServiceProvider::class)]
#[UsesClass(Database::class)]
#[UsesClass(DatabaseServiceProvider::class)]
#[UsesClass(MigrationServiceProvider::class)]
#[UsesClass(Migrator::class)]
#[UsesClass(RouterServiceProvider::class)]
#[UsesClass(Route::class)]
#[UsesClass(Router::class)]

#[UsesClass(DefaultExceptionHandler::class)]
#[UsesClass(ExceptionHandlerServiceProvider::class)]
#[UsesClass(ConsoleServiceProvider::class)]
#[UsesClass(Console::class)]
#[UsesClass(MigrateCommand::class)]
#[UsesClass(MigrateRollbackCommand::class)]
#[UsesClass(MakeMigrationCommand::class)]

#[UsesClass(MakeControllerCommand::class)]
#[UsesClass(MakeMiddlewareCommand::class)]
#[UsesClass(MakeProviderCommand::class)]
#[UsesClass(Input::class)]
#[UsesClass(Output::class)]
#[UsesClass(ServiceProvider::class)]
final class TranslatorServiceProviderTest extends DatabaseTestCase
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
