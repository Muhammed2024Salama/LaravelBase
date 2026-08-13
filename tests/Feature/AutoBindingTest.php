<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Tests\Feature;

use Illuminate\Support\Facades\File;
use MuhammedSalama\Base\BaseServiceProvider;
use MuhammedSalama\Base\Tests\TestCase;

class AutoBindingTest extends TestCase
{
    private const SPROCKET_INTERFACE = 'App\\Interfaces\\SprocketRepositoryInterface';

    private const GADGET_INTERFACE = 'App\\Interfaces\\GadgetRepositoryInterface';

    private const DOODAD_INTERFACE = 'App\\Interfaces\\DoodadRepositoryInterface';

    private const ORPHAN_INTERFACE = 'App\\Interfaces\\OrphanRepositoryInterface';

    private const SOME_CONTRACT = 'App\\Interfaces\\SomeContract';

    private const SPROCKET_REPOSITORY = 'App\\Repositories\\SprocketRepository';

    private const ELOQUENT_SPROCKET_REPOSITORY = 'App\\Repositories\\EloquentSprocketRepository';

    /** Fixtures are written on disk once; the class declarations survive the process. */
    private static bool $fixturesWritten = false;

    protected function getPackageProviders($app): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->writeFixtures();
    }

    protected function tearDown(): void
    {
        $appPath = app_path();

        foreach (array_keys(self::fixtureFiles()) as $relative) {
            File::delete($appPath.'/'.$relative);
        }

        parent::tearDown();
    }

    /**
     * @return array<string, string>
     */
    private static function fixtureFiles(): array
    {
        return [
            // Happy path: convention match.
            'Interfaces/SprocketRepositoryInterface.php' => "<?php\nnamespace App\\Interfaces;\ninterface SprocketRepositoryInterface {}\n",
            'Repositories/SprocketRepository.php' => "<?php\nnamespace App\\Repositories;\nclass SprocketRepository implements \\App\\Interfaces\\SprocketRepositoryInterface {}\n",
            // Alternative implementation, only reachable through config('base.bindings').
            'Repositories/EloquentSprocketRepository.php' => "<?php\nnamespace App\\Repositories;\nclass EloquentSprocketRepository implements \\App\\Interfaces\\SprocketRepositoryInterface {}\n",
            // Implementation exists but does NOT satisfy the contract.
            'Interfaces/GadgetRepositoryInterface.php' => "<?php\nnamespace App\\Interfaces;\ninterface GadgetRepositoryInterface { public function boom(): void; }\n",
            'Repositories/GadgetRepository.php' => "<?php\nnamespace App\\Repositories;\nclass GadgetRepository {}\n",
            // Implementation is abstract — binding it would blow up on resolve.
            'Interfaces/DoodadRepositoryInterface.php' => "<?php\nnamespace App\\Interfaces;\ninterface DoodadRepositoryInterface {}\n",
            'Repositories/DoodadRepository.php' => "<?php\nnamespace App\\Repositories;\nabstract class DoodadRepository implements \\App\\Interfaces\\DoodadRepositoryInterface {}\n",
            // Interface with no implementation at all — must not break boot.
            'Interfaces/OrphanRepositoryInterface.php' => "<?php\nnamespace App\\Interfaces;\ninterface OrphanRepositoryInterface {}\n",
            // Not a *RepositoryInterface — must be ignored entirely.
            'Interfaces/SomeContract.php' => "<?php\nnamespace App\\Interfaces;\ninterface SomeContract {}\n",
        ];
    }

    private function writeFixtures(): void
    {
        $appPath = app_path();

        File::ensureDirectoryExists($appPath.'/Interfaces');
        File::ensureDirectoryExists($appPath.'/Repositories');

        foreach (self::fixtureFiles() as $relative => $contents) {
            $path = $appPath.'/'.$relative;
            File::put($path, $contents);

            if (! self::$fixturesWritten) {
                require_once $path;
            }
        }

        self::$fixturesWritten = true;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function bootProvider(array $config): void
    {
        foreach ($config as $key => $value) {
            $this->app['config']->set('base.'.$key, $value);
        }

        $this->app->register(new BaseServiceProvider($this->app));
    }

    // -------------------------------------------------------------------------

    public function test_interface_is_auto_bound_to_conventional_repository(): void
    {
        $this->bootProvider(['auto_bind' => true, 'bindings' => []]);

        $this->assertInstanceOf(
            self::SPROCKET_REPOSITORY,
            $this->app->make(self::SPROCKET_INTERFACE)
        );
    }

    public function test_nothing_is_auto_bound_when_auto_bind_is_disabled(): void
    {
        $this->bootProvider(['auto_bind' => false, 'bindings' => []]);

        $this->assertFalse($this->app->bound(self::SPROCKET_INTERFACE));
    }

    /**
     * config('base.bindings') is documented as "always registered". Auto-binding
     * ran last and silently replaced explicit bindings with the convention match.
     */
    public function test_explicit_config_binding_wins_over_convention(): void
    {
        $this->bootProvider([
            'auto_bind' => true,
            'bindings' => [
                self::SPROCKET_INTERFACE => self::ELOQUENT_SPROCKET_REPOSITORY,
            ],
        ]);

        $this->assertInstanceOf(
            self::ELOQUENT_SPROCKET_REPOSITORY,
            $this->app->make(self::SPROCKET_INTERFACE)
        );
    }

    public function test_explicit_binding_is_registered_when_auto_bind_is_disabled(): void
    {
        $this->bootProvider([
            'auto_bind' => false,
            'bindings' => [
                self::SPROCKET_INTERFACE => self::ELOQUENT_SPROCKET_REPOSITORY,
            ],
        ]);

        $this->assertInstanceOf(
            self::ELOQUENT_SPROCKET_REPOSITORY,
            $this->app->make(self::SPROCKET_INTERFACE)
        );
    }

    public function test_class_that_does_not_implement_the_interface_is_not_bound(): void
    {
        $this->bootProvider(['auto_bind' => true, 'bindings' => []]);

        $this->assertFalse($this->app->bound(self::GADGET_INTERFACE));
    }

    public function test_abstract_implementation_is_not_bound(): void
    {
        $this->bootProvider(['auto_bind' => true, 'bindings' => []]);

        $this->assertFalse($this->app->bound(self::DOODAD_INTERFACE));
    }

    public function test_interface_without_implementation_does_not_break_boot(): void
    {
        $this->bootProvider(['auto_bind' => true, 'bindings' => []]);

        $this->assertFalse($this->app->bound(self::ORPHAN_INTERFACE));
        // Boot survived far enough to register the conventional binding.
        $this->assertTrue($this->app->bound(self::SPROCKET_INTERFACE));
    }

    public function test_non_repository_interfaces_are_ignored(): void
    {
        $this->bootProvider(['auto_bind' => true, 'bindings' => []]);

        $this->assertFalse($this->app->bound(self::SOME_CONTRACT));
    }

    /**
     * A non-string value used to be passed straight to bind(), which makes the
     * container try to instantiate the interface itself and fail far away from
     * the actual misconfiguration.
     */
    public function test_malformed_config_binding_is_skipped(): void
    {
        $this->bootProvider([
            'auto_bind' => false,
            'bindings' => [
                self::ORPHAN_INTERFACE => ['not', 'a', 'class'],
                self::DOODAD_INTERFACE => null,
                self::GADGET_INTERFACE => '',
            ],
        ]);

        $this->assertFalse($this->app->bound(self::ORPHAN_INTERFACE));
        $this->assertFalse($this->app->bound(self::DOODAD_INTERFACE));
        $this->assertFalse($this->app->bound(self::GADGET_INTERFACE));
    }

    public function test_missing_interfaces_directory_is_a_no_op(): void
    {
        File::deleteDirectory(app_path('Interfaces'));

        $this->bootProvider(['auto_bind' => true, 'bindings' => []]);

        $this->assertFalse($this->app->bound(self::SPROCKET_INTERFACE));
    }
}
