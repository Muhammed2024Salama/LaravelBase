<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Tests\Feature;

use App\Models\Widget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use MuhammedSalama\Base\Tests\TestCase;

class GeneratedModuleUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class DenyEverythingPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return false;
    }

    public function view(Authenticatable $user, mixed $model): bool
    {
        return false;
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, mixed $model): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, mixed $model): bool
    {
        return false;
    }
}

/**
 * Boots a freshly generated module end-to-end over real HTTP routes.
 *
 * The generated controller previously called $this->authorize(), which only
 * exists when the application's base controller uses AuthorizesRequests. The
 * Laravel 11+ skeleton dropped that trait, so every CRUD action fataled with
 * "Call to undefined method" on three of the four supported Laravel versions.
 * A stub-content assertion alone cannot catch that class of bug — the code has
 * to actually run.
 */
class GeneratedModuleRuntimeTest extends TestCase
{
    private const MODULE = 'Widget';

    private const MODEL = 'App\\Models\\Widget';

    /** @var list<string> */
    private array $generated = [];

    private static bool $autoloaderRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerAppAutoloader();
        $this->generateModule();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        $this->app->bind(
            'App\Interfaces\WidgetRepositoryInterface',
            'App\Repositories\WidgetRepository'
        );

        Route::apiResource('widgets', 'App\Http\Controllers\WidgetController');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('users');

        foreach ($this->generated as $path) {
            File::isDirectory($path) ? File::deleteDirectory($path) : File::delete($path);
        }

        parent::tearDown();
    }

    /**
     * The generated classes live in the Testbench skeleton, which no Composer
     * autoloader maps. Point App\ at it for the duration of the process.
     */
    private function registerAppAutoloader(): void
    {
        if (self::$autoloaderRegistered) {
            return;
        }

        $appPath = app_path();

        spl_autoload_register(static function (string $class) use ($appPath): void {
            if (! str_starts_with($class, 'App\\')) {
                return;
            }

            $file = $appPath.'/'.str_replace('\\', '/', substr($class, 4)).'.php';

            if (is_file($file)) {
                require_once $file;
            }
        });

        self::$autoloaderRegistered = true;
    }

    private function generateModule(): void
    {
        // The Laravel 11/12/13 application skeleton's base controller, verbatim.
        // It deliberately does NOT use AuthorizesRequests — that is exactly the
        // environment the generated controller has to work in.
        File::ensureDirectoryExists(app_path('Http/Controllers'));
        File::put(
            app_path('Http/Controllers/Controller.php'),
            "<?php\n\nnamespace App\\Http\\Controllers;\n\nabstract class Controller\n{\n    //\n}\n"
        );

        $this->generated = [
            app_path('Http/Controllers/Controller.php'),
            app_path('Interfaces/WidgetRepositoryInterface.php'),
            app_path('Repositories/WidgetRepository.php'),
            app_path('Models/Widget.php'),
            app_path('Enums/WidgetStatus.php'),
            app_path('Filters/WidgetFilters.php'),
            app_path('Services/WidgetService.php'),
            app_path('Http/Requests/Widget'),
            app_path('Http/Resources/WidgetResource.php'),
            app_path('Http/Resources/WidgetResourceCollection.php'),
            app_path('Policies/WidgetPolicy.php'),
            app_path('Http/Controllers/WidgetController.php'),
        ];

        $this->artisan('make:module', [
            'name' => self::MODULE,
            '--no-migration' => true,
            '--no-test' => true,
        ])->assertSuccessful();
    }

    private function actingAsUser(): static
    {
        return $this->actingAs(GeneratedModuleUser::create(['name' => 'Tester']));
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function seedWidgets(array $rows): void
    {
        foreach ($rows as $row) {
            Widget::create($row); // @phpstan-ignore-line
        }
    }

    // -------------------------------------------------------------------------
    // Authorization actually runs (and does not fatal)
    // -------------------------------------------------------------------------

    public function test_index_is_allowed_by_the_generated_policy(): void
    {
        $this->seedWidgets([['name' => 'Alpha'], ['name' => 'Beta']]);

        $response = $this->actingAsUser()->getJson('/widgets');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonStructure(['status', 'message', 'data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_returns_403_when_the_policy_denies(): void
    {
        Gate::policy(self::MODEL, DenyEverythingPolicy::class);

        $this->actingAsUser()->getJson('/widgets')->assertForbidden();
    }

    public function test_show_authorizes_the_individual_model(): void
    {
        $this->seedWidgets([['name' => 'Alpha']]);

        $this->actingAsUser()->getJson('/widgets/1')
            ->assertOk()
            ->assertJsonPath('data.name', 'Alpha');

        Gate::policy(self::MODEL, DenyEverythingPolicy::class);

        $this->actingAsUser()->getJson('/widgets/1')->assertForbidden();
    }

    public function test_destroy_authorizes_before_deleting(): void
    {
        $this->seedWidgets([['name' => 'Alpha']]);

        Gate::policy(self::MODEL, DenyEverythingPolicy::class);

        $this->actingAsUser()->deleteJson('/widgets/1')->assertForbidden();

        $this->assertDatabaseHas('widgets', ['name' => 'Alpha']);
    }

    // -------------------------------------------------------------------------
    // index() must not leak raw models
    // -------------------------------------------------------------------------

    public function test_index_rows_are_shaped_by_the_generated_resource(): void
    {
        $this->seedWidgets([['name' => 'Alpha']]);

        $response = $this->actingAsUser()->getJson('/widgets');

        $response->assertOk();

        /** @var array<string, mixed> $row */
        $row = $response->json('data.0');

        $this->assertSame(
            ['id', 'name', 'status', 'created_at', 'updated_at'],
            array_keys($row),
            'index() must return WidgetResource-shaped rows, not raw model attributes'
        );
    }

    // -------------------------------------------------------------------------
    // Filter wiring
    // -------------------------------------------------------------------------

    public function test_filter_pagination_and_sorting_are_wired_through_the_service(): void
    {
        $this->seedWidgets([
            ['name' => 'Alpha', 'status' => 'active'],
            ['name' => 'Beta', 'status' => 'inactive'],
            ['name' => 'Gamma', 'status' => 'active'],
        ]);

        $this->actingAsUser()->getJson('/widgets?status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->actingAsUser()->getJson('/widgets?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 3);

        $this->actingAsUser()->getJson('/widgets?sort_by=id&sort_dir=desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Gamma');
    }

    public function test_malformed_filter_input_does_not_produce_a_server_error(): void
    {
        $this->seedWidgets([['name' => 'Alpha']]);

        foreach (['?search[]=a&search[]=b', '?sort_by=id&sort_dir[]=desc', '?per_page[]=5'] as $query) {
            $this->actingAsUser()->getJson('/widgets'.$query)
                ->assertOk();
        }
    }

    public function test_unknown_record_returns_404_not_a_server_error(): void
    {
        $this->actingAsUser()->getJson('/widgets/999')->assertNotFound();
    }
}
