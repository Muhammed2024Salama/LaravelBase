<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use MuhammedSalama\Base\Filters\AbstractFilter;
use MuhammedSalama\Base\Tests\Fixtures\Post;
use MuhammedSalama\Base\Tests\TestCase;

class PostFilter extends AbstractFilter
{
    /** @var array<string, string> */
    protected array $filters = [
        'status' => '=',
        'title' => 'like',
    ];

    /** @var list<string> */
    protected array $sortable = ['id', 'title', 'status'];

    /** @var list<string> */
    protected array $searchable = ['title'];
}

class AbstractFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Post::create(['title' => 'Hello World', 'status' => 'active']);
        Post::create(['title' => 'Laravel Package', 'status' => 'inactive']);
        Post::create(['title' => 'Test Post', 'status' => 'active']);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('posts');
        parent::tearDown();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeFilter(array $params = []): PostFilter
    {
        $request = Request::create('/test', 'GET', $params);

        return new PostFilter($request, Post::query());
    }

    // ── filter by exact value ─────────────────────────────────────────────────

    public function test_exact_filter_returns_matching_rows(): void
    {
        $filter = $this->makeFilter(['status' => 'active']);
        $this->assertSame(2, $filter->getQuery()->count());
    }

    public function test_no_filter_params_returns_all_rows(): void
    {
        $filter = $this->makeFilter();
        $this->assertSame(3, $filter->getQuery()->count());
    }

    public function test_like_filter_returns_partial_matches(): void
    {
        $filter = $this->makeFilter(['title' => 'World']);
        $this->assertSame(1, $filter->getQuery()->count());
    }

    // ── non-whitelisted params are ignored (no SQL injection) ─────────────────

    public function test_unlisted_param_is_silently_ignored(): void
    {
        $filter = $this->makeFilter(['unknown_column' => 'value']);
        $this->assertSame(3, $filter->getQuery()->count());
    }

    // ── full-text search ──────────────────────────────────────────────────────

    public function test_search_matches_across_searchable_columns(): void
    {
        $filter = $this->makeFilter(['search' => 'Laravel']);
        $this->assertSame(1, $filter->getQuery()->count());
    }

    public function test_search_returns_all_matching_rows(): void
    {
        $filter = $this->makeFilter(['search' => 'Post']);
        $this->assertSame(1, $filter->getQuery()->count());
    }

    public function test_empty_search_param_returns_all(): void
    {
        $filter = $this->makeFilter(['search' => '']);
        $this->assertSame(3, $filter->getQuery()->count());
    }

    // ── sorting ───────────────────────────────────────────────────────────────

    public function test_sort_ascending_by_title(): void
    {
        $filter = $this->makeFilter(['sort_by' => 'title', 'sort_dir' => 'asc']);
        $results = $filter->getQuery()->pluck('title')->toArray();
        $sorted = $results;
        sort($sorted);
        $this->assertSame($sorted, $results);
    }

    public function test_sort_descending_by_title(): void
    {
        $filter = $this->makeFilter(['sort_by' => 'title', 'sort_dir' => 'desc']);
        $results = $filter->getQuery()->pluck('title')->toArray();
        $sorted = $results;
        rsort($sorted);
        $this->assertSame($sorted, $results);
    }

    public function test_unknown_sort_column_is_ignored(): void
    {
        $filter = $this->makeFilter(['sort_by' => 'injected; DROP TABLE posts;--']);
        $this->assertSame(3, $filter->getQuery()->count());
    }

    public function test_invalid_sort_dir_defaults_to_asc(): void
    {
        $filter = $this->makeFilter(['sort_by' => 'id', 'sort_dir' => 'invalid']);
        $results = $filter->getQuery()->pluck('id')->toArray();
        $sorted = $results;
        sort($sorted);
        $this->assertSame($sorted, $results);
    }

    // ── pagination ────────────────────────────────────────────────────────────

    public function test_paginate_returns_length_aware_paginator(): void
    {
        $paginator = $this->makeFilter()->paginate(2);

        $this->assertSame(3, $paginator->total());
        $this->assertSame(2, $paginator->perPage());
        $this->assertSame(2, $paginator->lastPage());
    }

    public function test_per_page_from_request_overrides_default(): void
    {
        $paginator = $this->makeFilter(['per_page' => '1'])->paginate(15);

        $this->assertSame(1, $paginator->perPage());
        $this->assertSame(3, $paginator->lastPage());
    }

    public function test_per_page_clamped_to_100_maximum(): void
    {
        $paginator = $this->makeFilter(['per_page' => '999'])->paginate();
        $this->assertSame(100, $paginator->perPage());
    }

    public function test_per_page_clamped_to_1_minimum(): void
    {
        $paginator = $this->makeFilter(['per_page' => '-5'])->paginate();
        $this->assertSame(1, $paginator->perPage());
    }

    // ── idempotency ───────────────────────────────────────────────────────────

    public function test_apply_is_idempotent(): void
    {
        $filter = $this->makeFilter(['status' => 'active']);

        $filter->apply();
        $filter->apply(); // calling again must not add duplicate WHERE clauses

        $this->assertSame(2, $filter->getQuery()->count());
    }

    public function test_get_query_and_paginate_both_work_on_same_filter(): void
    {
        $filter = $this->makeFilter(['status' => 'active']);
        $count = $filter->getQuery()->count();
        $paginator = $filter->paginate();

        $this->assertSame(2, $count);
        $this->assertSame(2, $paginator->total());
    }

    // ── malformed (array) input must never 500 ────────────────────────────────

    public function test_array_search_term_is_ignored_instead_of_erroring(): void
    {
        $filter = $this->makeFilter(['search' => ['Laravel', 'Hello']]);

        $this->assertSame(3, $filter->getQuery()->count());
    }

    public function test_array_value_on_like_filter_is_ignored_instead_of_erroring(): void
    {
        $filter = $this->makeFilter(['title' => ['World', 'Laravel']]);

        $this->assertSame(3, $filter->getQuery()->count());
    }

    public function test_array_sort_dir_falls_back_to_ascending(): void
    {
        $filter = $this->makeFilter(['sort_by' => 'id', 'sort_dir' => ['desc']]);

        $results = $filter->getQuery()->pluck('id')->toArray();
        $sorted = $results;
        sort($sorted);

        $this->assertSame($sorted, $results);
    }

    public function test_array_sort_by_is_ignored(): void
    {
        $filter = $this->makeFilter(['sort_by' => ['id']]);

        $this->assertSame(3, $filter->getQuery()->count());
    }

    public function test_array_value_on_exact_filter_is_ignored(): void
    {
        $filter = $this->makeFilter(['status' => ['active', 'inactive']]);

        $this->assertSame(3, $filter->getQuery()->count());
    }

    public function test_array_per_page_falls_back_to_a_valid_page_size(): void
    {
        $paginator = $this->makeFilter(['per_page' => ['5']])->paginate();

        $this->assertGreaterThanOrEqual(1, $paginator->perPage());
        $this->assertLessThanOrEqual(100, $paginator->perPage());
    }

    // ── sort column injection ─────────────────────────────────────────────────

    public function test_sort_by_injection_payloads_are_rejected(): void
    {
        $payloads = [
            'id; DROP TABLE posts',
            'id) UNION SELECT 1,2,3 --',
            'id`,(SELECT 1)`',
            '(CASE WHEN 1=1 THEN id ELSE title END)',
            'posts.id',
        ];

        foreach ($payloads as $payload) {
            $filter = $this->makeFilter(['sort_by' => $payload]);

            $this->assertSame(3, $filter->getQuery()->count(), "payload: {$payload}");
            $this->assertStringNotContainsStringIgnoringCase(
                'order by',
                $filter->getQuery()->toSql(),
                "payload: {$payload}"
            );
        }
    }

    public function test_filter_values_are_bound_not_interpolated(): void
    {
        $filter = $this->makeFilter(['status' => "active' OR '1'='1"]);

        $this->assertSame(0, $filter->getQuery()->count());
    }

    // ── combined filters + search ──────────────────────────────────────────────

    public function test_filter_and_search_combined(): void
    {
        $filter = $this->makeFilter(['status' => 'active', 'search' => 'Hello']);
        $this->assertSame(1, $filter->getQuery()->count());
    }
}
