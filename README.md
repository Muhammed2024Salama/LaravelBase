<h1 align="center">Laravel Base</h1>

<p align="center">
  One command. A complete, production-ready API module.<br>
  Model &middot; Migration &middot; Enum &middot; Filter &middot; Repository &middot; Service
  &middot; Requests &middot; Resource &middot; Policy &middot; Controller &middot; Tests
</p>

<p align="center">
  <a href="https://packagist.org/packages/muhammedsalama/laravel-base">
    <img src="https://img.shields.io/packagist/v/muhammedsalama/laravel-base?style=flat-square" alt="Latest Version on Packagist">
  </a>
  <a href="https://packagist.org/packages/muhammedsalama/laravel-base">
    <img src="https://img.shields.io/packagist/dt/muhammedsalama/laravel-base?style=flat-square" alt="Total Downloads">
  </a>
  <a href="https://github.com/MuhammedMSalama/LaravelBase/actions/workflows/ci.yml">
    <img src="https://img.shields.io/github/actions/workflow/status/MuhammedMSalama/LaravelBase/ci.yml?branch=main&style=flat-square&label=tests" alt="Tests">
  </a>
  <a href="https://github.com/MuhammedMSalama/LaravelBase/actions/workflows/ci.yml">
    <img src="https://img.shields.io/badge/PHPStan-level%205-brightgreen?style=flat-square" alt="PHPStan Level 5">
  </a>
  <a href="https://codecov.io/gh/MuhammedMSalama/LaravelBase">
    <img src="https://img.shields.io/codecov/c/github/MuhammedMSalama/LaravelBase/main?style=flat-square&label=coverage" alt="Code Coverage">
  </a>
  <a href="https://packagist.org/packages/muhammedsalama/laravel-base">
    <img src="https://img.shields.io/packagist/php-v/muhammedsalama/laravel-base?style=flat-square" alt="PHP Version">
  </a>
  <a href="LICENSE">
    <img src="https://img.shields.io/badge/license-MIT-green?style=flat-square" alt="License: MIT">
  </a>
  <a href="https://github.com/MuhammedMSalama/LaravelBase/actions">
    <img src="https://img.shields.io/github/actions/workflow/status/MuhammedMSalama/LaravelBase/ci.yml?branch=main&style=flat-square&label=CI" alt="CI">
  </a>
</p>

<p align="center">
  <img src="docs/demo.gif" alt="make:module Product demo" width="700">
</p>

---

```bash
php artisan make:module Product
```

One command generates **15 files** across every layer of your REST API:

| Layer | What is generated |
|---|---|
| Data | Model, driver-aware migration, repository interface, repository |
| Domain | Service, filter class (whitelist-safe column filtering + pagination) |
| HTTP | Controller (Swagger-annotated), store &amp; update form requests, API resource, resource collection |
| Auth | Policy with CRUD gates, wired into every controller action |
| Status | PHP 8.1 backed string enum (`active / inactive / pending`), auto-cast by the model |
| Tests | Feature test + unit test stubs — pre-skipped so CI is green from commit one |

Then add one route:

```php
Route::apiResource('products', ProductController::class);
```

Your API is running, documented, authorized, and tested.

---

## Table of Contents

- [Why LaravelBase?](#why-laravelbase)
- [Features](#features)
- [Laravel Artisan vs LaravelBase](#laravel-artisan-vs-laravelbase)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Architecture overview](#architecture-overview)
- [make:module — the module generator](#makemodule--the-module-generator)
  - [Component table](#component-table)
  - [--only and --except](#--only-and---except)
  - [Per-component --no-* flags](#per-component---no--flags)
  - [Other options](#other-options)
- [Filter + Pagination API](#filter--pagination-api)
- [Driver-aware migrations](#driver-aware-migrations)
- [Swagger / OpenAPI docs](#swagger--openapi-docs)
- [Enums](#enums)
- [Policy](#policy)
- [API Resource](#api-resource)
- [Generated tests](#generated-tests)
- [Repository auto-binding](#repository-auto-binding)
- [make:repository — deprecated alias](#makerepository--deprecated-alias)
- [base:create-database](#basecreate-database)
- [Manual setup](#manual-setup)
- [API Reference](#api-reference)
  - [RepositoryInterface](#repositoryinterface)
  - [BaseRepository](#baserepository)
  - [ServiceInterface](#serviceinterface)
  - [BaseService](#baseservice)
  - [BaseRequest](#baserequest)
  - [ApiResponse](#apiresponse)
  - [ApiResponseTrait](#apiresponsetrait)
  - [ImageUploadTrait](#imageuploadtrait)
- [Configuration](#configuration)
- [Publishing helpers and traits](#publishing-helpers-and-traits)
- [Testing and static analysis](#testing-and-static-analysis)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)
- [Author](#author)

---

## Why LaravelBase?

Every Laravel API project ends up writing the same boilerplate: a repository, a service, a form request that returns JSON errors, and a handful of response helpers. You write them once, then copy-paste across every project.

LaravelBase eliminates that tax. The package ships pre-written, production-hardened base classes and a generator that scaffolds a complete vertical slice — not just the model, not just the controller, but every layer wired together and ready to ship:

- **No `ServiceProvider` edits for the common case** — interfaces are auto-bound to implementations by naming convention at boot.
- **No column-injection vulnerabilities** — `AbstractFilter` accepts only columns you explicitly whitelist; unknown request parameters are silently ignored.
- **No documentation debt** — generated controllers carry `@OA` Swagger annotations; install `l5-swagger` and your API is self-documented.
- **No broken CI** — generated test stubs are pre-skipped so your pipeline is green from the first commit.
- **Your code, your rules** — generated files live in `app/`; edit, extend, or delete them freely. They are never re-generated unless you pass `--force`.

The generated code is idiomatic Laravel — no framework-within-a-framework, no magic, no lock-in.

---

## Features

| Feature | Details |
|---|---|
| **Module generator** | `make:module` scaffolds 15 components in one command: Model, Migration, Enum, Filter, Interface, Repository, Service, Store/Update Requests, API Resource + Collection, Policy, Controller, Feature test, Unit test |
| **Filter + Pagination** | `AbstractFilter` base class: whitelist-safe column filters, LIKE search, sort-by whitelist, per-page clamping — ergonomic `$service->filter($request)->paginate()` API |
| **Driver-aware migrations** | Detects MySQL / PostgreSQL / SQLite at runtime; uses `json()` on MySQL/PG, `text()` fallback otherwise |
| **Swagger / OpenAPI** | Generated controllers carry `@OA\*` PHPDoc annotations (l5-swagger / swagger-php compatible) — optional, gated behind `suggest` |
| **Status Enums** | PHP 8.1 backed string enum (`Active`, `Inactive`, `Pending`) with `label()`, `isActive()`, `values()`; model casts `status` automatically |
| **Policy** | Full CRUD policy stub (`viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`); the generated controller calls `Gate::authorize()` for every action |
| **API Resource** | `JsonResource` + `ResourceCollection` with `@OA\Schema`; controller returns wrapped resources via `ApiResponse` |
| **Generated tests** | Feature + Unit test stubs that pass (skipped) from the first commit; inline TODO instructions |
| **Repository pattern** | `BaseRepository` with `all`, `paginate`, `find`, `findOrFail`, `findBy`, `create`, `update`, `delete`, `query` |
| **Service layer** | `BaseService` wrapping any repository; extend to add domain logic |
| **Contracts-first design** | `RepositoryInterface` / `ServiceInterface` with full native type hints |
| **External validation** | `BaseRequest` returns a standard 422 JSON envelope on failure — no extra code |
| **Consistent API responses** | Static `ApiResponse` helper + `ApiResponseTrait` — identical JSON shape everywhere |
| **Image handling** | `ImageUploadTrait` — upload / update / delete with a strict image MIME allow-list; non-image payloads are rejected outright |
| **Auto-binding** | `*RepositoryInterface` auto-bound to `*Repository` by naming convention — no manual registration |
| **Laravel 10/11/12/13, PHP 8.1+** | No upper-bound constraint on Laravel version |

---

## Laravel Artisan vs LaravelBase

| Capability | Laravel Artisan (built-in) | LaravelBase v3 |
|---|---|---|
| Generate Eloquent model | `make:model` | Included in `make:module` by default |
| Generate migration | `make:migration` (separate command) | Auto-generated, **driver-aware** — `json()` on MySQL/PG, `text()` with notice on SQLite |
| Repository pattern | Not provided | `BaseRepository` + `RepositoryInterface` + generated `{Name}Repository` + `{Name}RepositoryInterface` |
| Service layer | Not provided | `BaseService` + `ServiceInterface` + generated `{Name}Service` |
| Controller | `make:controller --api` (empty method bodies) | Full CRUD controller with `Gate::authorize()`, typed requests, `ApiResponse` calls, and Swagger annotations |
| Form Requests | `make:request` (separate command, manual wiring) | `Store{Name}Request` + `Update{Name}Request` generated and wired; `BaseRequest` returns JSON 422 envelope automatically |
| JSON API response helper | Not provided | `ApiResponse` — `success` / `created` / `error` / `notFound` / `paginated` with a consistent `{status, message, data, meta}` envelope |
| Status Enum | Not provided | PHP 8.1 backed string enum with `label()`, `isActive()`, `values()`; model casts `status` automatically |
| Query filtering + pagination | Not provided | `AbstractFilter`: whitelisted column filters, LIKE search, global `?search=`, sort whitelist, `?per_page=` clamped to [1, 100] |
| API Resource + Collection | `make:resource` (separate command) | `{Name}Resource` + `{Name}ResourceCollection` generated and wired into controller; includes `@OA\Schema` annotation |
| Policy | `make:policy` (separate command, manual registration) | `{Name}Policy` with all CRUD gates; controller calls `Gate::authorize()` for every action |
| Swagger / OpenAPI docs | Not provided | All 5 CRUD actions annotated (`@OA\Get/Post/Put/Delete`); compatible with `darkaonline/l5-swagger` |
| Repository auto-binding | Not provided | Convention-based: `*RepositoryInterface` → `*Repository` resolved at boot; no `ServiceProvider` edit required |
| Explicit provider binding | Not provided | `--provider` flag creates/updates `RepositoryServiceProvider`; idempotent on re-runs |
| Generated tests | Not provided | Feature + Unit test stubs, pre-skipped (CI stays green), with inline TODO instructions |
| Database creation command | Not provided | `base:create-database [--connection=]` — MySQL and PostgreSQL |
| Image upload helper | Not provided | `ImageUploadTrait`: extension chosen from an allow-list keyed by the detected MIME type; anything that is not a raster image is refused |
| Subset generation | N/A | `--only=` / `--except=` — generate any combination of the 15 components |
| Per-component skip flags | N/A | `--no-model`, `--no-migration`, `--no-enum`, `--no-filter`, `--no-service`, `--no-request`, `--no-resource`, `--no-policy`, `--no-controller`, `--no-test` |
| Laravel version support | Current only | 10, 11, 12, 13 |
| PHP version support | Current only | 8.1, 8.2, 8.3, 8.4 |
| CI matrix | N/A | PHP 8.1–8.4 × Laravel 10–13 + prefer-lowest + PHPStan level 5 + `make:module` smoke test |

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.1` |
| Laravel | `10.x` and above (10 / 11 / 12 / 13) |

---

## Installation

```bash
composer require muhammedsalama/laravel-base
```

The service provider registers automatically via Laravel's package auto-discovery. No manual configuration required.

<details>
<summary>Install from GitHub (VCS repository)</summary>

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/MuhammedMSalama/LaravelBase"
    }
]
```

```bash
composer require muhammedsalama/laravel-base:^3.0
```

</details>

<details>
<summary>Install from a local path (package development)</summary>

```json
"repositories": [
    {
        "type": "path",
        "url": "../laravel-base"
    }
],
"require": {
    "muhammedsalama/laravel-base": "*"
}
```

</details>

---

## Quick Start

Generate a complete `Product` module with one command:

```bash
php artisan make:module Product
```

This creates 15 files — the full vertical slice of a REST API module:

```
app/
  Enums/ProductStatus.php
  Filters/ProductFilters.php
  Interfaces/ProductRepositoryInterface.php
  Repositories/ProductRepository.php
  Services/ProductService.php
  Models/Product.php
  Policies/ProductPolicy.php
  Http/
    Controllers/ProductController.php
    Requests/Product/
      StoreProductRequest.php
      UpdateProductRequest.php
    Resources/
      ProductResource.php
      ProductResourceCollection.php
database/migrations/xxxx_xx_xx_create_products_table.php
tests/
  Feature/ProductTest.php
  Unit/ProductServiceTest.php
```

Register the route, define your `$fillable`, register the policy, and you have a working, documented, authorized CRUD API:

```php
// routes/api.php
Route::apiResource('products', ProductController::class);
```

---

## Architecture overview

```
HTTP Request
    │
    ▼
Controller              (thin — delegates, authorizes, transforms)
    │  uses ApiResponse::
    │  type-hints Form Requests (Store/Update)
    │  calls Gate::authorize() via Policy
    │  returns ApiResource-wrapped data
    ▼
Filters class           (AbstractFilter — request-driven, whitelisted)
    │  applies WHERE / LIKE / ORDER BY to the query
    │  paginates and returns LengthAwarePaginator
    ▼
Service                 (domain logic)
    │  extends BaseService
    │  exposes filter(Request): {Name}Filters
    │  depends on RepositoryInterface
    ▼
Repository              (data access)
    │  extends BaseRepository
    │  wraps an Eloquent Model
    ▼
Model                   (casts status → {Name}Status enum)
    │
    ▼
Database
```

Validation failures are caught by `BaseRequest::failedValidation()` before the controller runs, and a 422 `ApiResponse` envelope is returned automatically.

---

## make:module — the module generator

```bash
php artisan make:module {Name} [options]
```

Generates a complete module. All files are valid, idiomatic, and immediately working.

### Component table

| Component | Generated path | Stub |
|---|---|---|
| Interface | `app/Interfaces/{Name}RepositoryInterface.php` | `interface.stub` |
| Repository | `app/Repositories/{Name}Repository.php` | `repository.stub` |
| Model | `app/Models/{Name}.php` | `module-model.stub` / `model.stub` |
| Migration | `database/migrations/{ts}_create_{table}_table.php` | `migration.stub` |
| Status Enum | `app/Enums/{Name}Status.php` | `enum.stub` |
| Filters | `app/Filters/{Name}Filters.php` | `filter.stub` |
| Service | `app/Services/{Name}Service.php` | `module-service.stub` / `service.stub` |
| StoreRequest | `app/Http/Requests/{Name}/Store{Name}Request.php` | `request.stub` |
| UpdateRequest | `app/Http/Requests/{Name}/Update{Name}Request.php` | `request.stub` |
| API Resource | `app/Http/Resources/{Name}Resource.php` | `resource.stub` |
| ResourceCollection | `app/Http/Resources/{Name}ResourceCollection.php` | `resource-collection.stub` |
| Policy | `app/Policies/{Name}Policy.php` | `policy.stub` |
| Controller | `app/Http/Controllers/{Name}Controller.php` | `module-controller.stub` |
| Feature test | `tests/Feature/{Name}Test.php` | `test-feature.stub` |
| Unit test | `tests/Unit/{Name}ServiceTest.php` | `test-unit.stub` |

The `module-model.stub` (includes enum cast) is used when `enum` is enabled (the default). The `module-service.stub` (includes `filter()` method) is used when `filter` is enabled. The `module-controller.stub` (full: Resources + Policy + Swagger) is used when `resource`, `request`, `filter`, and `policy` are all enabled. Otherwise the simpler `controller.stub` / `controller.plain.stub` are selected automatically.

### --only and --except

Generate a **subset** of components using `--only` (whitelist) or `--except` (blacklist). Both accept comma-separated component names from the table above.

```bash
# Only the data-access layer
php artisan make:module Invoice --only=model,migration,interface,repository,service

# Everything except tests
php artisan make:module Invoice --except=test

# Just the model and migration — quick prototyping
php artisan make:module Order --only=model,migration

# Skip policy and tests — lean module
php artisan make:module Category --except=policy,test
```

`--only` takes priority over `--except`. When both are omitted, all components are generated.

When the requested subset leaves a generated file referring to a class that was not
generated and does not already exist (for example `--only=controller`, where the
controller type-hints `{Name}Service`), the command lists the missing classes instead of
failing silently — the files are still written, so you can regenerate one layer of an
existing module.

The module name is normalised with `Str::studly`, so `user-profile` and `user_profile`
both become `UserProfile`. Names that cannot become a valid PHP class name — digits
first, path separators, reserved keywords such as `class` or `match` — are rejected with
an error and nothing is written.

### Per-component --no-* flags

| Flag | Skips | Side effect |
|---|---|---|
| `--no-model` | Model | Model is **never** overwritten even with `--force` |
| `--no-migration` | Migration | |
| `--no-enum` | Status Enum | Uses plain `model.stub` (no cast) |
| `--no-filter` | Filters class | Uses plain `service.stub` (no `filter()` method) and `controller.stub` |
| `--no-service` | Service | The controller still type-hints `{Name}Service`; the command warns about the dangling reference |
| `--no-request` | Store + Update Requests | Uses `controller.plain.stub` |
| `--no-resource` | API Resource + Collection | Falls back to `controller.stub` |
| `--no-policy` | Policy | Falls back to `controller.stub` |
| `--no-controller` | Controller | |
| `--no-test` | Feature + Unit test stubs | |

### Other options

| Option | Description |
|---|---|
| `--model=Foo` | Use `Foo` as the Eloquent model name (default: same as module name) |
| `--controller=FooController` | Custom controller class name |
| `--provider` | Create / update `RepositoryServiceProvider` with a binding for the interface |
| `--force` | Overwrite existing files (model is **never** overwritten regardless). An existing `create_{table}_table` migration is rewritten in place, not duplicated under a new timestamp |

---

## Filter + Pagination API

Every generated module ships with a `{Name}Filters` class extending
`MuhammedSalama\Base\Filters\AbstractFilter`, and the generated `{Name}Service` exposes a
`filter(Request $request)` method pre-wired to the repository's query builder.

### Usage in the controller

```php
// {Name}Controller::index() — generated automatically
public function index(Request $request): JsonResponse
{
    Gate::authorize('viewAny', Product::class);

    $paginator = $this->service->filter($request)->paginate();

    return ApiResponse::paginated(
        $paginator->through(fn (Product $product) => new ProductResource($product))
    );
}
```

### Declaring filterable fields

Extend `AbstractFilter` and declare your whitelist:

```php
// app/Filters/ProductFilters.php
class ProductFilters extends AbstractFilter
{
    // column => SQL operator ('=', 'like', '>', '<', '>=', '<=', '!=')
    protected array $filters = [
        'status'   => '=',     // ?status=active
        'category' => '=',
        'name'     => 'like',  // ?name=phone → WHERE name LIKE '%phone%'
    ];

    // columns the client may ORDER BY
    protected array $sortable = ['id', 'name', 'price', 'created_at'];

    // columns searched by a single ?search=term
    protected array $searchable = ['name', 'description'];
}
```

### Supported request parameters

| Parameter | Behaviour |
|---|---|
| `?status=active` | Exact match — operator must be `'='` in `$filters` |
| `?name=phone` | LIKE match — operator must be `'like'` in `$filters` |
| `?search=keyword` | OR LIKE across all `$searchable` columns |
| `?sort_by=name` | ORDER BY — column must be in `$sortable` |
| `?sort_dir=desc` | Sort direction; anything other than `desc` defaults to `asc` |
| `?per_page=25` | Page size; clamped to `[1, 100]`; default 15 |

**Security:** column names and sort directions never come from the request. Only columns
declared in `$filters` or `$sortable` are applied, the operator comes from your `$filters`
map, `sort_dir` is normalised to `asc`/`desc`, and every value is passed as a bound
parameter. Unknown request parameters are silently ignored, and array-valued parameters
(`?search[]=a&search[]=b`, `?sort_dir[]=desc`) are ignored rather than raising an error.

One thing the filter deliberately does **not** do: `%` and `_` inside a `like` filter or a
`?search=` term are passed through to SQL as wildcards, matching Laravel's own `where(...,
'like', ...)` behaviour. That is not injection — the value is still bound — but a caller
can broaden their own match. Escape the term yourself if you need a literal `%`.

### AbstractFilter API

```php
// Apply filters and return the Builder for further custom constraints
$builder = $filter->apply()->getQuery();

// Apply filters and paginate (returns LengthAwarePaginator)
$paginator = $filter->paginate($perPage = 15);

// Standard one-liner
return ApiResponse::paginated($this->service->filter($request)->paginate());
```

> `paginate()` reads `?per_page=` from the request and clamps it to `[1, 100]`; the
> argument is only the fallback when the request does not supply one.

`apply()` is idempotent — safe to call multiple times without duplicating WHERE clauses.

---

## Driver-aware migrations

`make:module` reads `config('database.default')` and the configured driver at runtime — it never hardcodes a driver. The `metadata` JSON column in the generated migration is emitted as:

| Driver | Generated column |
|---|---|
| `mysql` | `$table->json('metadata')->nullable()` |
| `pgsql` | `$table->json('metadata')->nullable()` |
| `sqlite` / other | `$table->text('metadata')->nullable()` + a printed notice |

The `status` column always uses `$table->string('status')` — portable across all drivers. If your project uses SQLite or another driver, a notice is printed at generation time so you can review the migration before running it.

---

## Swagger / OpenAPI docs

The generated controller carries `@OA\...` PHPDoc annotations compatible with
**[darkaonline/l5-swagger](https://github.com/DarkaOnLine/L5-Swagger)** +
**[zircote/swagger-php](https://github.com/zircote/swagger-php)**. These packages are listed
in `composer.json` under `suggest` only — they are **not required**. Your application works
without them; the annotations are inert PHPDoc comments unless you install the generator.

### Optional setup

```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

Add a global `@OA\Info` annotation once — conventionally in `app/Http/Controllers/Controller.php`:

```php
/**
 * @OA\Info(title="My API", version="1.0.0")
 */
class Controller extends BaseController { ... }
```

Generate the docs and open the interactive Swagger UI:

```bash
php artisan l5-swagger:generate
# → http://your-app/api/documentation
```

### What is annotated in each generated action

| Method | Annotation | Documents |
|---|---|---|
| `index` | `@OA\Get` | path, `per_page`, `search`, `sort_by`, `sort_dir`, `status` params; 200/401/403 |
| `show` | `@OA\Get` | path `{id}` param; 200/404 |
| `store` | `@OA\Post` | 201/422 |
| `update` | `@OA\Put` | path `{id}`; 200/422 |
| `destroy` | `@OA\Delete` | path `{id}`; 200/404 |

The generated `{Name}Resource` carries a `@OA\Schema` annotation with all declared properties.

---

## Enums

The generated `{Name}Status` is a PHP 8.1 backed string enum with three starter cases and
helper methods:

```php
// app/Enums/ProductStatus.php
enum ProductStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Pending  = 'pending';

    public function label(): string { ... }       // "Active", "Inactive", "Pending"
    public function isActive(): bool { ... }       // true when === self::Active
    public static function values(): array { ... } // ['active', 'inactive', 'pending']
}
```

The generated model casts `status` automatically:

```php
// app/Models/Product.php
protected $casts = [
    'status' => ProductStatus::class,
];
```

Usage examples:

```php
$product->status;                      // ProductStatus::Active
$product->status->label();             // "Active"
$product->status->value;               // "active"
$product->status->isActive();          // true
ProductStatus::values();               // ['active', 'inactive', 'pending']

// In StoreProductRequest:
'status' => ['required', \Illuminate\Validation\Rule::enum(ProductStatus::class)],
```

---

## Policy

The generated `{Name}Policy` starts with all gates open (`return true`), except
`forceDelete()` which starts closed. Harden the logic to match your business rules
before production.

### Registration

```php
// Option A — AuthServiceProvider (Laravel 10 / 11):
protected $policies = [
    Product::class => ProductPolicy::class,
];

// Option B — AppServiceProvider / boot() (any version):
\Illuminate\Support\Facades\Gate::policy(Product::class, ProductPolicy::class);
```

The generated controller calls `Gate::authorize()` for every action automatically:

```php
use Illuminate\Support\Facades\Gate;

Gate::authorize('viewAny', Product::class);  // index
Gate::authorize('view',    $product);        // show
Gate::authorize('create',  Product::class);  // store
Gate::authorize('update',  $product);        // update
Gate::authorize('delete',  $product);        // destroy
```

The `Gate` facade is used rather than `$this->authorize()` on purpose: the latter
only exists when the application's base controller uses
`Illuminate\Foundation\Auth\Access\AuthorizesRequests`, which the Laravel 11+
skeleton no longer does. `Gate::authorize()` behaves identically on Laravel 10
through 13 and throws the same `AuthorizationException` (HTTP 403).

> Authorization is generated for the **full module controller** only. Reduced
> controllers (produced when `--no-policy`, `--no-resource`, `--no-filter` or
> `--no-request` is used) contain no authorization calls, because the policy they
> would reference may not exist — add your own.

---

## API Resource

`{Name}Resource` extends `JsonResource` with a `toArray()` ready to customise.
`{Name}ResourceCollection` wraps it for list responses.

The generated controller returns:

```php
// Single resource
return ApiResponse::success(new ProductResource($product));

// Created
return ApiResponse::created(new ProductResource($product));

// Paginated list (via the filter's paginator) — rows are wrapped in the
// resource too, so index() never returns raw model attributes
$paginator = $this->service->filter($request)->paginate();

return ApiResponse::paginated(
    $paginator->through(fn (Product $product) => new ProductResource($product))
);
```

---

## Generated tests

Both test stubs are **skipped** out of the box so your CI is green from the first commit.
Each method has inline `TODO` instructions.

```bash
php artisan test tests/Feature/ProductTest.php      # all skipped ✓
php artisan test tests/Unit/ProductServiceTest.php  # all skipped ✓
```

Enable the feature tests by:

1. Registering the route: `Route::apiResource('products', ProductController::class);`
2. Creating a factory: `php artisan make:factory ProductFactory --model=Product`
3. Removing the `markTestSkipped()` calls

---

## Repository auto-binding

By default (`auto_bind => true` in `config/base.php`), the package scans
`app/Interfaces/*RepositoryInterface.php` at boot and binds each to its matching
`app/Repositories/*Repository.php` — no manual registration required.

A binding is only registered when the implementation exists, is concrete, and actually
implements the interface. An interface with no implementation, a half-written class, or a
name collision is skipped rather than breaking application boot. Anything you declare in
`config('base.bindings')` always wins over the convention.

To manage bindings explicitly, use `--provider` when generating:

```bash
php artisan make:module Product --provider
# Creates app/Providers/RepositoryServiceProvider.php (once)
# and appends the binding for Product.
# Subsequent --provider runs are idempotent — no duplicate entries.
```

Then register the provider once:

```php
// bootstrap/providers.php  (Laravel 11+)
App\Providers\RepositoryServiceProvider::class,

// config/app.php  (Laravel 10)
'providers' => [App\Providers\RepositoryServiceProvider::class],
```

Or disable auto-binding entirely and declare bindings in config:

```php
// config/base.php
'auto_bind' => false,
'bindings'  => [
    \App\Interfaces\ProductRepositoryInterface::class
        => \App\Repositories\ProductRepository::class,
],
```

---

## make:repository — deprecated alias

> **Deprecated since v3.0.0.** `make:repository` will continue to work indefinitely
> for backward compatibility, but **new projects should use `make:module`**.

`make:repository` is a thin wrapper that calls `make:module` with all new components
suppressed (`--no-resource --no-policy --no-test --no-enum --no-filter`), so the generated
output is **identical** to what v2.x produced. All existing options (`--model`,
`--controller`, `--no-service`, `--no-controller`, `--no-request`, `--no-migration`,
`--provider`, `--force`) are forwarded transparently.

A deprecation notice is printed at runtime:

```
⚠  make:repository is deprecated. Please use `php artisan make:module` instead.
```

```bash
# These all still work exactly as before:
php artisan make:repository Product
php artisan make:repository BlogPost --model=Post --no-migration
php artisan make:repository Order --controller=OrderApiController
```

---

## base:create-database

Creates the configured database if it does not already exist. Supports MySQL and PostgreSQL.

```bash
php artisan base:create-database [--connection=]
```

| Option | Description |
|---|---|
| `--connection=` | Laravel database connection name (defaults to `database.default`) |

```bash
php artisan base:create-database              # default connection
php artisan base:create-database --connection=pgsql
```

- If the database already exists, the command exits successfully without changes.
- MySQL: uses `charset` and `collation` from the connection config.
- PostgreSQL: uses `charset` as the `ENCODING`.
- Requires the matching PDO extension and a user with `CREATE DATABASE` privileges.

---

## Manual setup

The generator covers most cases, but here is how to wire a module by hand if needed.

### Interface

```php
namespace App\Interfaces;

use MuhammedSalama\Base\Interfaces\RepositoryInterface;

interface ProductRepositoryInterface extends RepositoryInterface
{
    // Add product-specific query methods here when needed.
}
```

### Repository

```php
namespace App\Repositories;

use App\Interfaces\ProductRepositoryInterface;
use App\Models\Product;
use MuhammedSalama\Base\Repositories\BaseRepository;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }
}
```

### Service

```php
namespace App\Services;

use App\Filters\ProductFilters;
use App\Interfaces\ProductRepositoryInterface;
use Illuminate\Http\Request;
use MuhammedSalama\Base\Services\BaseService;

class ProductService extends BaseService
{
    public function __construct(ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function filter(Request $request): ProductFilters
    {
        return new ProductFilters($request, $this->repository->query());
    }
}
```

### Form Requests

```php
namespace App\Http\Requests\Product;

use MuhammedSalama\Base\Requests\BaseRequest;

class StoreProductRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name'   => 'required|string|max:255',
            'price'  => 'required|numeric|min:0',
            'status' => ['required', \Illuminate\Validation\Rule::enum(\App\Enums\ProductStatus::class)],
        ];
    }
}
```

A failed validation response:

```json
{
    "status": false,
    "message": "Validation error",
    "errors": {
        "name": ["The name field is required."]
    }
}
```

### Controller

```php
namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MuhammedSalama\Base\Helpers\ApiResponse;

class ProductController extends Controller
{
    public function __construct(private ProductService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Product::class);
        return ApiResponse::paginated($this->service->filter($request)->paginate());
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->service->find($id);
        Gate::authorize('view', $product);
        return ApiResponse::success(new ProductResource($product));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        Gate::authorize('create', Product::class);
        return ApiResponse::created(new ProductResource($this->service->store($request->validated())));
    }

    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $product = $this->service->find($id);
        Gate::authorize('update', $product);
        return ApiResponse::success(new ProductResource($this->service->update($id, $request->validated())));
    }

    public function destroy(int $id): JsonResponse
    {
        $product = $this->service->find($id);
        Gate::authorize('delete', $product);
        $this->service->destroy($id);
        return ApiResponse::success(null, 'Product deleted successfully.');
    }
}
```

---

## API Reference

### RepositoryInterface

`MuhammedSalama\Base\Interfaces\RepositoryInterface`

| Method | Returns | Notes |
|---|---|---|
| `all(array $columns, array $relations)` | `Collection<int, Model>` | Eager-loads `$relations` |
| `paginate(int $perPage, array $columns, array $relations)` | `LengthAwarePaginator` | |
| `find(int\|string $id, array $columns, array $relations)` | `?Model` | `null` when absent |
| `findOrFail(int\|string $id, array $columns, array $relations)` | `Model` | Throws `ModelNotFoundException` |
| `findBy(string $column, mixed $value, array $columns)` | `?Model` | First match |
| `create(array $data)` | `Model` | Respects `$fillable`/`$guarded` |
| `update(int\|string $id, array $data)` | `Model` | Finds, updates, returns model |
| `delete(int\|string $id)` | `bool` | Throws if absent |
| `query()` | `Builder` | Fresh query builder for complex queries |

### BaseRepository

`MuhammedSalama\Base\Repositories\BaseRepository` — implements `RepositoryInterface`.

Extend it and inject the Eloquent model via the constructor:

```php
class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }
}
```

Additional method not in the interface:

| Method | Returns |
|---|---|
| `getModel(): Model` | The raw model instance |

### ServiceInterface

`MuhammedSalama\Base\Interfaces\ServiceInterface`

| Method | Returns | Notes |
|---|---|---|
| `all(array $columns, array $relations)` | `Collection<int, Model>` | |
| `paginate(int $perPage, array $columns, array $relations)` | `LengthAwarePaginator` | |
| `find(int\|string $id, array $columns, array $relations)` | `Model` | Always throws on missing |
| `store(array $data)` | `Model` | |
| `update(int\|string $id, array $data)` | `Model` | |
| `destroy(int\|string $id)` | `bool` | |

### BaseService

`MuhammedSalama\Base\Services\BaseService` — implements `ServiceInterface`.

Extend it and inject the repository interface:

```php
class ProductService extends BaseService
{
    public function __construct(ProductRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
```

Additional method not in the interface:

| Method | Returns |
|---|---|
| `repository(): RepositoryInterface` | The underlying repository (for custom queries) |

### BaseRequest

`MuhammedSalama\Base\Requests\BaseRequest` — extends Laravel's `FormRequest`.

| Member | Default | Notes |
|---|---|---|
| `authorize()` | `true` | Override to add gate/policy checks |
| `rules()` | — | Define validation rules (abstract-like) |
| `failedValidation()` | — | Throws `HttpResponseException` with 422 envelope |

### ApiResponse

`MuhammedSalama\Base\Helpers\ApiResponse` — all methods return `JsonResponse`.

```php
ApiResponse::success($data, 'Message');           // 200
ApiResponse::created($data);                       // 201
ApiResponse::noContent();                          // 204
ApiResponse::error('Message', 400, $errors);
ApiResponse::validation($errors);                  // 422
ApiResponse::notFound('Message');                  // 404
ApiResponse::unauthorized();                       // 401
ApiResponse::forbidden();                          // 403
ApiResponse::paginated($paginator, 'Message');     // 200 + meta
```

**Standard success envelope:**

```json
{ "status": true, "message": "Success", "data": { ... } }
```

**Standard error envelope:**

```json
{ "status": false, "message": "Validation error", "errors": { ... } }
```

**Paginated envelope:**

```json
{
    "status": true, "message": "Success",
    "data": [ ... ],
    "meta": { "current_page": 1, "last_page": 5, "per_page": 15, "total": 72 }
}
```

### ApiResponseTrait

`MuhammedSalama\Base\Traits\ApiResponseTrait` — use inside controllers to call response
methods as `$this->success(...)` instead of `ApiResponse::success(...)`. Produces the
identical JSON envelope.

| Method | Status |
|---|---|
| `success($data, $message, $code)` | 200 |
| `created($data, $message)` | 201 |
| `error($message, $code, $errors)` | 400 |
| `validationError($errors, $message)` | 422 |
| `notFound($message)` | 404 |
| `unauthorized($message)` | 401 |
| `forbidden($message)` | 403 |
| `paginated($paginator, $message)` | 200 |

### ImageUploadTrait

`MuhammedSalama\Base\Traits\ImageUploadTrait` — the stored extension comes from an
allow-list keyed by the *detected* MIME type (finfo, on the file contents), never from
the client-supplied filename. Anything outside the allow-list is rejected and no file is
written, so an HTML, SVG or PHP payload can never reach the web root under a
script-capable extension.

| Method | Returns | Description |
|---|---|---|
| `uploadImage($request, $input, $path)` | `string\|null` | Store a single image; `null` when the input is absent or not an allowed image |
| `uploadMultiImage($request, $input, $path)` | `array<int, string>` | Store multiple images; rejected files are skipped |
| `updateImage($request, $input, $path, $oldPath)` | `string\|null` | Replace an image; the old file is deleted **only after** the new one is written |
| `deleteImage($path)` | `void` | Delete an image |

**Accepted types:** `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/avif`,
`image/bmp`, `image/tiff`. Everything else — including SVG, which is scriptable — returns
`null` and writes nothing. Override `allowedImageMimeTypes(): array` in the consuming
class to change the map:

```php
protected function allowedImageMimeTypes(): array
{
    return parent::allowedImageMimeTypes() + ['image/heic' => 'heic'];
}
```

`$path` is resolved relative to `public_path()`; any `..` traversal segment is rejected.

```php
// Store
$path = $this->uploadImage($request, 'image', 'uploads/products');

// Replace (old file is deleted automatically)
$path = $this->updateImage($request, 'image', 'uploads/products', $product->image);

// Delete
$this->deleteImage($product->image);
```

The trait is a hard backstop, not a substitute for validation — it cannot tell the user
*why* an upload was refused. Still validate in the Form Request so the client gets a 422:

```php
'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
```

---

## Configuration

Publish the config:

```bash
php artisan vendor:publish --tag=base-config
```

This creates `config/base.php`:

```php
return [
    // Auto-bind App\Interfaces\{Name}RepositoryInterface → App\Repositories\{Name}Repository
    'auto_bind' => true,

    // Explicit bindings — always registered, and they take precedence over
    // anything auto_bind resolved by convention.
    'bindings'  => [
        // \App\Interfaces\ProductRepositoryInterface::class
        //     => \App\Repositories\EloquentProductRepository::class,
    ],
];
```

---

## Publishing helpers and traits

By default, `ApiResponse` and the traits are used directly from the package namespace — no copying needed. If you want **editable local copies** under `App\`:

```bash
php artisan vendor:publish --tag=base-helpers  # → app/Helpers/ApiResponse.php
php artisan vendor:publish --tag=base-traits   # → app/Traits/ApiResponseTrait.php + ImageUploadTrait.php
php artisan vendor:publish --tag=base-config   # → config/base.php
```

After publishing, switch `use` statements to the `App\Helpers` / `App\Traits` namespaces. Published files will not receive automatic updates — treat them as your own code.

---

## Testing and static analysis

```bash
composer install
composer test     # PHPUnit + Orchestra Testbench (SQLite in-memory)
composer analyse  # PHPStan level 5 + Larastan
```

The CI matrix covers PHP 8.1–8.4 × Laravel 10/11/12/13 on every push and pull request.

---

## Troubleshooting

**`Deprecation Notice: Function curl_close() is deprecated` during Composer.**
These come from Composer running on PHP 8.5+. Run `composer self-update` or use PHP 8.4.

**`Your requirements could not be resolved … nette/schema requires php 8.1 - 8.4`.**
A transitive dependency predates PHP 8.5. Update it: `composer update nette/schema --with-all-dependencies`.

**Auto-binding does not seem to work.**
Verify that `app/Interfaces/` exists and files match `*RepositoryInterface.php`. Both the interface class and the implementation must be autoloadable (`class_exists()` in `tinker`). Confirm `auto_bind => true` in `config/base.php`.

**`Class … not found` after running `make:module`.**
Run `composer dump-autoload` so the PSR-4 autoloader discovers the newly created files.

**Policy gates throw `AuthorizationException` on every request.**
The generated policy starts with all gates open (`return true`). If you bound the policy but all requests fail, check that your `Auth::user()` is set (routes are authenticated). If running API tests unauthenticated, call `$this->withoutMiddleware()` or mock the Gate facade.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a full list of changes by version.

---

## Contributing

Contributions are welcome. Please:

1. Fork the repository and create a feature branch.
2. Write tests for any change in behaviour.
3. Ensure `composer test` and `composer analyse` both pass locally.
4. Open a pull request against `main` with a clear description of the change.
5. **CI must be green before merging.** The workflow runs `composer test` across PHP 8.1–8.4 and Laravel 10/11/12/13 (with appropriate PHP-version excludes), plus PHPStan static analysis.

---

## Security

If you discover a security vulnerability, please use [GitHub private vulnerability reporting](https://github.com/MuhammedMSalama/LaravelBase/security/advisories/new) or contact the maintainer at <devmuhammedsalama@gmail.com>. Do **not** disclose vulnerabilities publicly until they have been addressed.

---

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

---

## Author

**Muhammed Salama** — [@MuhammedMSalama](https://github.com/MuhammedMSalama)
