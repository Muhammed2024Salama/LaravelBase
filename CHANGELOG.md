# Changelog

All notable changes to `laravel-base` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- **`ImageUploadTrait` no longer writes non-image uploads into the web root.** The
  stored extension is now taken from an allow-list keyed by the *detected* MIME type;
  anything else is refused and no file is written. Previously the extension came from
  `UploadedFile::extension()` unfiltered, so a `text/html` payload became
  `public/…/media_*.html` and an `image/svg+xml` payload became `…​.svg` — stored XSS on
  the application's own origin — while a PHP payload could resolve to `.php` on hosts
  whose `fileinfo` reports `application/x-httpd-php`. Destination paths are also
  normalised and `..` traversal segments rejected.
- **`updateImage()` no longer deletes the previous file before the replacement
  succeeds.** A rejected or failed upload used to destroy the existing image.
- **The reduced (`controller.plain.stub`) controller no longer forwards raw request
  input.** `$request->except(['_token', '_method'])` went straight into a model
  generated with `$guarded = []`; it now passes `$request->validate(...)` output.
- **`make:module` validates the module, model and controller names.** `make:module
  ../../evil` previously wrote outside `app/`, and names like `123abc` or `class`
  produced files that do not parse.
- **`AbstractFilter` ignores array-valued parameters** instead of raising
  "Array to string conversion". `?search[]=a`, `?sort_dir[]=desc` and an array on a
  `like` filter were request-triggerable 500s on every filtered endpoint.

### Fixed
- **Generated controllers use `Gate::authorize()` instead of `$this->authorize()`.**
  The latter only exists when the application's base controller uses
  `AuthorizesRequests`, which the Laravel 11+ skeleton dropped — every CRUD action
  fataled with "Call to undefined method" on Laravel 11, 12 and 13.
- **`index()` wraps rows in `{Name}Resource`.** It previously returned raw models, so
  the list endpoint exposed every column regardless of the resource definition.
- **`--force` rewrites the existing `create_{table}_table` migration in place** instead
  of adding a second, freshly timestamped one that broke `php artisan migrate`.
- **`config('base.bindings')` now takes precedence over convention auto-binding.**
  Explicit bindings were registered first and then silently overwritten.
- **Auto-binding validates its candidates.** A binding is only registered when the
  implementation exists, is concrete, and actually implements the interface; malformed
  entries in `config('base.bindings')` are skipped instead of binding an interface to
  `null`. Interface names containing "Interface" more than once now map correctly.
- **`ApiResponseTrait` gained the documented `unauthorized()` and `forbidden()`
  methods**, which existed in the published stub but not in the package trait.
- **`make:module` reports dangling references.** Subsets such as `--only=controller` or
  `--no-service` emit files referencing classes that were never generated; the command
  now lists them instead of failing silently.

### Changed
- README corrected where it described behaviour the code did not have: the policy stub
  does not use `HandlesAuthorization`, authorization is generated for the full module
  controller only, and the LIKE-wildcard passthrough is now documented explicitly.

## [3.0.1] - 2026-06-18

### Added
- Community health files: Code of Conduct, issue templates, pull request
  template, and CODEOWNERS.
- GitHub Discussions and repository topic recommendations under `docs/`.
- EditorConfig rules for UTF-8, LF line endings, four-space indentation, and
  Markdown whitespace handling.

### Changed
- Improved package metadata, Packagist discovery keywords, support links, and
  distribution export rules.
- Strengthened README badges and security reporting instructions.
- Expanded contributor guidance for issue templates and the pull request
  workflow.

### Infrastructure
- Documented release infrastructure and static analysis visibility through
  README badges and repository metadata.
- Excluded development-only files from package distribution archives.

## [3.0.0] - 2026-06-05

### Version reasoning
v3.0.0 because:
- `make:module` is a new primary command that generates a materially larger set of
  artifacts than `make:repository` ever did. Calling it "2.x" would misrepresent the
  scope of the change.
- `make:repository` now delegates to `make:module` internally. Its *output* is fully
  backward-compatible (the same files are generated when the same `--no-*` flags are
  used), but the command is now marked `@deprecated` and prints a deprecation notice
  at runtime — a visible behavior change that merits a major bump.
- The new components (Filter, Enum, Policy, Resource, Tests, Swagger annotations)
  represent a fundamentally different capability surface.

### Added
- **`php artisan make:module {Name}`** — the new primary generator that scaffolds a
  complete module in one command:
  | Component | Generated path | Stub used |
  |---|---|---|
  | Interface | `app/Interfaces/{Name}RepositoryInterface.php` | `interface.stub` |
  | Repository | `app/Repositories/{Name}Repository.php` | `repository.stub` |
  | Model | `app/Models/{Name}.php` | `module-model.stub` / `model.stub` |
  | Migration | `database/migrations/{ts}_create_{table}_table.php` | `migration.stub` |
  | Status Enum | `app/Enums/{Name}Status.php` | `enum.stub` |
  | Filters class | `app/Filters/{Name}Filters.php` | `filter.stub` |
  | Service | `app/Services/{Name}Service.php` | `module-service.stub` / `service.stub` |
  | StoreRequest | `app/Http/Requests/{Name}/Store{Name}Request.php` | `request.stub` |
  | UpdateRequest | `app/Http/Requests/{Name}/Update{Name}Request.php` | `request.stub` |
  | API Resource | `app/Http/Resources/{Name}Resource.php` | `resource.stub` |
  | ResourceCollection | `app/Http/Resources/{Name}ResourceCollection.php` | `resource-collection.stub` |
  | Policy | `app/Policies/{Name}Policy.php` | `policy.stub` |
  | Controller | `app/Http/Controllers/{Name}Controller.php` | `module-controller.stub` / `controller.stub` / `controller.plain.stub` |
  | Feature test | `tests/Feature/{Name}Test.php` | `test-feature.stub` |
  | Unit test | `tests/Unit/{Name}ServiceTest.php` | `test-unit.stub` |
- **`--only=<list>`** and **`--except=<list>`** options: comma-separated component
  names to include or exclude (e.g. `--only=model,migration` or
  `--except=test,enum`).
- **`--no-enum`**, **`--no-filter`**, **`--no-resource`**, **`--no-policy`**,
  **`--no-test`** options for per-component opt-out (complement the existing
  `--no-model`, `--no-service`, `--no-controller`, `--no-request`, `--no-migration`).
- **`MuhammedSalama\Base\Filters\AbstractFilter`** — base class for request-driven
  query filtering with built-in pagination:
  - Declare `$filters` (column → operator whitelist), `$sortable`, `$searchable` in
    each subclass.
  - Supports `=`, `like`, `>`, `<`, `>=`, `<=`, `!=` operators.
  - `?search=` applies LIKE across all `$searchable` columns.
  - `?sort_by=` + `?sort_dir=asc|desc` for safe, whitelisted ordering.
  - `?per_page=` clamped to `[1, 100]`; `paginate(int $default = 15)` returns a
    `LengthAwarePaginator` compatible with `ApiResponse::paginated()`.
  - `apply()` is idempotent; `getQuery()` returns the filtered `Builder` for custom
    post-processing.
- **Driver-aware migration generation**: reads `config('database.default')` and the
  configured driver at runtime.
  - MySQL / PostgreSQL: `$table->json('metadata')->nullable()`.
  - SQLite / other: `$table->text('metadata')->nullable()` with a printed notice.
  - The `status` column uses `string` type everywhere (portable; no driver-specific
    enum constraints).
- **Swagger / OpenAPI doc-block annotations** in the generated controller
  (`@OA\Get`, `@OA\Post`, `@OA\Put`, `@OA\Delete`, `@OA\Tag`, `@OA\Parameter`,
  `@OA\Response`) compatible with `darkaonline/l5-swagger` + `zircote/swagger-php`.
  The package is suggested (not required); see README for setup.
- **Policy** stub with `HandlesAuthorization`, all standard CRUD gates, and
  instructions for registration in `AuthServiceProvider` / via `Gate::policy()`.
  The full-module controller wires `$this->authorize()` calls for every action.
- **Status Enum** (`{Name}Status: string`) with `Active`, `Inactive`, `Pending`
  cases; `label()`, `isActive()`, and `values()` helpers. The generated model casts
  the `status` column to the enum automatically.
- **API Resource** + **ResourceCollection** with `@OA\Schema` annotation and a
  `toArray()` implementation ready to customise.
- **Feature and Unit test stubs** that pass (skipped) out-of-the-box so the
  consumer's CI stays green from first commit; each has inline TODO instructions.
- New `module-model.stub`, `module-service.stub`, `module-controller.stub` for the
  full module experience; existing stubs are unchanged for backward compatibility.
- 17 new package tests covering: full module generation, `--only`/`--except`,
  individual `--no-*` flags, idempotency, `--force`, backward-compat alias,
  enum content, provider binding.
- 19 new `AbstractFilterTest` assertions covering exact filters, LIKE filters,
  unknown-column injection protection, full-text search, sorting, pagination
  clamping, idempotency, and combined filter+search.
- `composer.json` `suggest` block for `darkaonline/l5-swagger` and
  `zircote/swagger-php`; updated `keywords` and `description`.

### Changed
- **`make:repository`** is now a thin `@deprecated` alias that delegates to
  `make:module` with `--no-resource --no-policy --no-test --no-enum --no-filter`.
  All existing options (`--model`, `--controller`, `--no-*`, `--provider`, `--force`)
  are forwarded transparently. **Output is identical to v2.2.0** — no files are
  added or removed compared to previous runs. A deprecation notice is printed at
  runtime pointing to `make:module`.

### Migration guide from v2.x
1. No code changes required — `make:repository` still works and generates the same
   files as before.
2. Switch scripts to `php artisan make:module` at your convenience; the output is a
   superset of what `make:repository` produced.
3. If you have a custom class implementing `RepositoryInterface` directly (without
   extending `BaseRepository`), no interface changes were made in this release —
   `query(): Builder` was already present since v2.0.0.

---

## [2.2.0] - 2026-06-02

### Added
- `make:repository` now generates the Eloquent model automatically when it does not
  exist (`app/Models/{Model}.php`). The generated model has `protected $guarded = []`
  with a TODO comment so `create()`/`update()` work out of the box. The model is
  **never overwritten** regardless of `--force`; pass `--no-model` to skip generation
  entirely.
- `--no-model` option: skip Eloquent model generation.
- `--provider` option: create (or update in place) `app/Providers/RepositoryServiceProvider.php`
  and insert an explicit `$this->app->bind(…)` for the generated interface/repository pair.
  - The provider is generated once and never overwritten.
  - Bindings are idempotent — re-running for the same name will not duplicate entries.
  - New bindings are inserted above a persistent `// {{ bindings }}` marker so the
    insertion point is always predictable.
  - The command prints the exact line to add to `bootstrap/providers.php` (Laravel 11+)
    or `config/app.php` (Laravel 10).
  - When both `auto_bind` and `--provider` are active the bindings are identical and
    harmless; set `auto_bind => false` in `config/base.php` to make the provider the
    sole binding mechanism.
- New stubs: `model.stub`, `repository-service-provider.stub`.
- 10 new tests covering model creation, model preservation, `--no-model` skip,
  `--force` not overwriting the model, provider creation, binding append, no-duplicate
  guarantee, existing provider preservation, and `--provider`-absent guard.

## [2.1.0] - 2026-06-02

### Added
- Laravel 13 compatibility (Orchestra Testbench 11, PHP ^8.3 for the L13 CI jobs).
  - `illuminate/*` constraints were already open-ended (`>=10.0`) and continue to accept Laravel 13.
  - `orchestra/testbench` updated to `^8.0|^9.0|^10.0|^11.0`.
  - `phpunit/phpunit` updated to `^10.5|^11.0|^12.0` to unblock Testbench 9/10/11's
    preference for PHPUnit 12.
  - CI matrix gains a `13.*` Laravel axis (PHP 8.3 and 8.4 only; PHP 8.1/8.2 excluded
    because Testbench 11 requires `^8.3`).

### Fixed
- CI badge in README was pointing to the wrong GitHub repository slug (`laravel-base` →
  `LaravelBase`); both the `<a href>` and the `<img src>` have been corrected.

## [2.0.0] - 2026-06-01

### Breaking changes
- `RepositoryInterface` and `ServiceInterface` methods now carry native PHP return types and typed
  parameters (`int|string $id`, `mixed $value`, `Collection`, `LengthAwarePaginator`, `Model`,
  `?Model`, `Builder`). Any class that implements these interfaces or overrides methods in
  `BaseRepository` / `BaseService` must add matching return-type declarations.

### Security
- `CreateDatabaseCommand`: database name, charset, collation, and PostgreSQL encoding are now
  validated against a strict allowlist (`^[A-Za-z0-9_\-]+$`) before being interpolated into SQL.
  Previously only the quoting character was stripped, leaving charset/collation unguarded.
- `ImageUploadTrait` (src + stubs): replaced `getClientOriginalExtension()` with `extension()`.
  The new method derives the file extension from the actual MIME type via `finfo`, preventing
  extension-spoofing attacks (e.g. a PHP shell named `shell.php.jpg`).
- `controller.plain.stub`: `$request->all()` replaced with `$request->except(['_token', '_method'])`
  and a prominent `TODO` comment directing developers to add validation rules.

### Fixed
- Published `ApiResponseTrait` stub was missing `unauthorized()` (401) and `forbidden()` (403)
  methods that exist in the package-side trait; callers using the published copy would get a fatal
  error at runtime.
- `BaseRepository::update()` issued a redundant `->fresh()` SELECT after every update. Eloquent
  already syncs model attributes in memory after `update()`; the extra query has been removed.
- Removed a misleading PHPDoc comment in `BaseRepository` describing an optional `model(): string`
  pattern the class does not actually support.
- `ServiceInterface::find()` PHPDoc now correctly states that the method throws
  `ModelNotFoundException` (it calls `findOrFail`, not `find`).

### Added
- Native return types and typed parameters across `RepositoryInterface`, `ServiceInterface`,
  `BaseRepository`, and `BaseService`.
- Test suite expanded from 7 to 27 tests (60 assertions):
  - `BaseRequestTest`: `authorize()` default, `failedValidation()` produces 422 envelope, error
    messages contain field-level detail.
  - `BaseServiceTest`: `store`, `find`, `all`, `update`, `destroy`, `repository()` accessor,
    `paginate`.
  - `MakeRepositoryCommandTest`: file generation, skipping existing files, `--force` overwrite,
    `--controller` custom name.
  - `CreateDatabaseCommandTest`: unconfigured connection, unsupported driver, empty database name,
    invalid database name characters, invalid charset characters.
- `phpunit.xml` added to `.gitignore` so it no longer shadows `phpunit.xml.dist`.

## [1.3.0] - 2026-06-01
### Added
- `make:repository` now also generates `Store{Name}Request` and `Update{Name}Request` Form Requests and wires them into the controller.
- `make:repository` now generates a `create_{table}_table` migration (skips if one already exists).
- New `base:create-database` command that creates the configured database if missing, supporting MySQL and PostgreSQL.
- Test suite (PHPUnit + Orchestra Testbench), PHPStan/Larastan static analysis, and GitHub Actions CI workflows.

## [1.2.0]
### Added
- Controller generation in `make:repository`, with an optional custom name.
- Publishable copies of the `ApiResponse` helper and the traits (`base-helpers`, `base-traits` tags).

## [1.1.0]
### Added
- `make:repository` command to scaffold the interface, repository, and service.
- Convention-based auto-binding of repository interfaces to implementations.

## [1.0.0]
### Added
- Initial release: `RepositoryInterface`/`ServiceInterface` contracts, `BaseRepository`, `BaseService`, `BaseRequest`, the `ApiResponse` helper, and the `ApiResponseTrait` and `ImageUploadTrait` traits.
