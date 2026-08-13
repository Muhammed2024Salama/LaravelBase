<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Tests\Unit;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use MuhammedSalama\Base\Helpers\ApiResponse;
use MuhammedSalama\Base\Tests\TestCase;
use MuhammedSalama\Base\Traits\ApiResponseTrait;

class ApiResponseTest extends TestCase
{
    public function test_success_returns_the_standard_envelope(): void
    {
        $response = ApiResponse::success(['id' => 1], 'OK');
        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue((bool) $payload['status']);
        $this->assertSame('OK', $payload['message']);
        $this->assertSame(['id' => 1], $payload['data']);
    }

    public function test_created_uses_201(): void
    {
        $response = ApiResponse::created(['id' => 5]);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function test_error_returns_a_failure_envelope(): void
    {
        $response = ApiResponse::error('Nope', 400, ['field' => 'bad']);
        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertFalse((bool) $payload['status']);
        $this->assertSame('Nope', $payload['message']);
        $this->assertSame(['field' => 'bad'], $payload['errors']);
    }

    public function test_documented_status_codes(): void
    {
        $this->assertSame(204, ApiResponse::noContent()->getStatusCode());
        $this->assertSame(422, ApiResponse::validation(['a' => ['b']])->getStatusCode());
        $this->assertSame(404, ApiResponse::notFound()->getStatusCode());
        $this->assertSame(401, ApiResponse::unauthorized()->getStatusCode());
        $this->assertSame(403, ApiResponse::forbidden()->getStatusCode());
    }

    public function test_error_envelope_never_carries_a_data_key(): void
    {
        foreach ([ApiResponse::notFound(), ApiResponse::unauthorized(), ApiResponse::forbidden()] as $response) {
            /** @var array<string, mixed> $payload */
            $payload = $response->getData(true);

            $this->assertSame(['status', 'message', 'errors'], array_keys($payload));
            $this->assertFalse((bool) $payload['status']);
            $this->assertNull($payload['errors']);
        }
    }

    public function test_paginated_envelope_exposes_only_the_documented_meta_keys(): void
    {
        $paginator = new LengthAwarePaginator([['id' => 1], ['id' => 2]], 72, 15, 1);

        $response = ApiResponse::paginated($paginator);
        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['status', 'message', 'data', 'meta'], array_keys($payload));
        /** @var array<string, mixed> $meta */
        $meta = $payload['meta'];
        $this->assertSame(['current_page', 'last_page', 'per_page', 'total'], array_keys($meta));
        $this->assertSame(72, $meta['total']);
        $this->assertSame(5, $meta['last_page']);
    }

    // -------------------------------------------------------------------------
    // ApiResponseTrait must expose everything the README documents, with the
    // same shapes as the static helper.
    // -------------------------------------------------------------------------

    public function test_trait_exposes_every_documented_method(): void
    {
        $responder = new class
        {
            use ApiResponseTrait;

            /** @return array{int, array<string, mixed>} */
            public function call(string $method, mixed ...$args): array
            {
                /** @var JsonResponse $response */
                $response = $this->{$method}(...$args);

                /** @var array<string, mixed> $payload */
                $payload = $response->getData(true);

                return [$response->getStatusCode(), $payload];
            }
        };

        foreach (['success', 'created', 'error', 'validationError', 'notFound', 'unauthorized', 'forbidden', 'paginated'] as $method) {
            $this->assertTrue(method_exists($responder, $method), "ApiResponseTrait::{$method}() is documented but missing");
        }

        $this->assertSame(200, $responder->call('success')[0]);
        $this->assertSame(201, $responder->call('created')[0]);
        $this->assertSame(400, $responder->call('error')[0]);
        $this->assertSame(422, $responder->call('validationError', ['a' => ['b']])[0]);
        $this->assertSame(404, $responder->call('notFound')[0]);
        $this->assertSame(401, $responder->call('unauthorized')[0]);
        $this->assertSame(403, $responder->call('forbidden')[0]);
    }

    public function test_trait_and_helper_produce_identical_envelopes(): void
    {
        $responder = new class
        {
            use ApiResponseTrait;

            /** @return array<string, mixed> */
            public function ok(): array
            {
                /** @var array<string, mixed> $payload */
                $payload = $this->success(['id' => 1], 'Hi')->getData(true);

                return $payload;
            }
        };

        /** @var array<string, mixed> $expected */
        $expected = ApiResponse::success(['id' => 1], 'Hi')->getData(true);

        $this->assertSame($expected, $responder->ok());
    }
}
