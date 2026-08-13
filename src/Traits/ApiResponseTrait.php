<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use MuhammedSalama\Base\Helpers\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Use this trait inside controllers to call $this->success(...) directly,
 * instead of the static ApiResponse::success(...) helper. Both produce
 * the same JSON shape.
 */
trait ApiResponseTrait
{
    protected function success(mixed $data = null, ?string $message = 'Success', int $code = Response::HTTP_OK): JsonResponse
    {
        return ApiResponse::success($data, $message, $code);
    }

    protected function error(?string $message = 'Something went wrong', int $code = Response::HTTP_BAD_REQUEST, mixed $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $code, $errors);
    }

    protected function validationError(mixed $errors, ?string $message = 'Validation error'): JsonResponse
    {
        return ApiResponse::validation($errors, $message);
    }

    protected function notFound(?string $message = 'Resource not found'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function unauthorized(?string $message = 'Unauthorized'): JsonResponse
    {
        return ApiResponse::unauthorized($message);
    }

    protected function forbidden(?string $message = 'Forbidden'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }

    protected function created(mixed $data = null, ?string $message = 'Created successfully'): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    /** @param LengthAwarePaginator<int, mixed> $paginator */
    protected function paginated(LengthAwarePaginator $paginator, ?string $message = 'Success'): JsonResponse
    {
        return ApiResponse::paginated($paginator, $message);
    }
}
