<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;

abstract class BaseApiController extends Controller
{
    protected function success(mixed $data = null, string $message = null, int $code = 200, array $meta = []): JsonResponse
    {
        return ApiResponse::success($data, $message, $code, $meta);
    }

    protected function created(mixed $data = null, string $message = null): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function noContent(): JsonResponse
    {
        return ApiResponse::noContent();
    }

    protected function error(string $message, int $code = 400, mixed $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $code, $errors);
    }

    protected function notFound(string $message = 'المورد غير موجود'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function forbidden(string $message = 'غير مصرح'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }

    protected function paginated(LengthAwarePaginator|CursorPaginator $paginator, string $key = 'items'): JsonResponse
    {
        return ApiResponse::paginated($paginator, $key);
    }
}
