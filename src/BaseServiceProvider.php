<?php

declare(strict_types=1);

namespace MuhammedSalama\Base;

use Illuminate\Support\ServiceProvider;
use MuhammedSalama\Base\Console\Commands\CreateDatabaseCommand;
use MuhammedSalama\Base\Console\Commands\MakeModuleCommand;
use MuhammedSalama\Base\Console\Commands\MakeRepositoryCommand;
use ReflectionClass;

class BaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/base.php',
            'base'
        );

        /** @var array<string, mixed> $configured */
        $configured = (array) config('base.bindings', []);

        if (config('base.auto_bind', true)) {
            $this->autoBindRepositories(array_keys($configured));
        }

        foreach ($configured as $interface => $implementation) {
            if (! is_string($implementation) || $implementation === '') {
                continue;
            }

            $this->app->bind((string) $interface, $implementation);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/base.php' => config_path('base.php'),
            ], 'base-config');

            $this->publishes([
                __DIR__.'/../stubs/app/Helpers/ApiResponse.php' => app_path('Helpers/ApiResponse.php'),
            ], 'base-helpers');

            $this->publishes([
                __DIR__.'/../stubs/app/Traits/ApiResponseTrait.php' => app_path('Traits/ApiResponseTrait.php'),
                __DIR__.'/../stubs/app/Traits/ImageUploadTrait.php' => app_path('Traits/ImageUploadTrait.php'),
            ], 'base-traits');

            $this->commands([
                MakeModuleCommand::class,
                MakeRepositoryCommand::class,
                CreateDatabaseCommand::class,
            ]);
        }
    }

    /**
     * Automatically bind every *RepositoryInterface in app/Interfaces
     * to its matching *Repository in app/Repositories by naming convention.
     *
     * A binding is only registered when the implementation actually exists,
     * is concrete, and genuinely implements the interface — so a half-written
     * class or a name collision can never break application boot or silently
     * register a contract the implementation does not satisfy.
     *
     * @param  list<string>  $skip  Interfaces already bound explicitly via config.
     */
    protected function autoBindRepositories(array $skip = []): void
    {
        $interfacePath = app_path('Interfaces');

        if (! is_dir($interfacePath)) {
            return;
        }

        $appNamespace = $this->app->getNamespace();
        $skip = array_flip(array_map(static fn (string $fqcn): string => ltrim($fqcn, '\\'), $skip));

        foreach ((glob($interfacePath.'/*RepositoryInterface.php') ?: []) as $file) {
            $base = basename($file, '.php');                       // ProductRepositoryInterface
            $interface = $appNamespace.'Interfaces\\'.$base;        // App\Interfaces\ProductRepositoryInterface

            if (isset($skip[$interface])) {
                continue;
            }

            $implementation = $appNamespace.'Repositories\\'.substr($base, 0, -strlen('Interface'));

            if (! interface_exists($interface) || ! class_exists($implementation)) {
                continue;
            }

            if (! is_subclass_of($implementation, $interface)) {
                continue;
            }

            if (! (new ReflectionClass($implementation))->isInstantiable()) {
                continue;
            }

            $this->app->bind($interface, $implementation);
        }
    }
}
