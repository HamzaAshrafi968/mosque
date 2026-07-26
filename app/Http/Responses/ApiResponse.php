<?php

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $code = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $code);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, Response::HTTP_CREATED);
    }

    public static function noContent(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public static function error(string $message, int $code = Response::HTTP_BAD_REQUEST, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    public static function notFound(string $message = 'المورد غير موجود'): JsonResponse
    {
        return self::error($message, Response::HTTP_NOT_FOUND);
    }

    public static function forbidden(string $message = 'غير مصرح'): JsonResponse
    {
        return self::error($message, Response::HTTP_FORBIDDEN);
    }

    public static function unauthenticated(string $message = 'غير مصدق'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNAUTHORIZED);
    }

    public static function validationError(mixed $errors, string $message = 'بيانات غير صالحة'): JsonResponse
    {
        return self::error($message, Response::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }

    /**
     * @param  LengthAwarePaginator|CursorPaginator  $paginator
     */
    public static function paginated(mixed $paginator, string $key = 'items'): JsonResponse
    {
        $meta = [];
        $data = [];
        $items = $paginator;

        if ($paginator instanceof ResourceCollection) {
            $resource = $paginator->resource;
            if ($resource instanceof LengthAwarePaginator) {
                $items = json_decode($paginator->toJson());
                $items = $items->data ?? $items;
                $meta = [
                    'current_page' => $resource->currentPage(),
                    'last_page' => $resource->lastPage(),
                    'per_page' => $resource->perPage(),
                    'total' => $resource->total(),
                    'from' => $resource->firstItem(),
                    'to' => $resource->lastItem(),
                ];
                $data[$key] = $items;

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'meta' => $meta,
                ]);
            }
            if ($resource instanceof CursorPaginator) {
                $items = json_decode($paginator->toJson());
                $items = $items->data ?? $items;
                $meta = [
                    'per_page' => $resource->perPage(),
                    'next_cursor' => $resource->nextCursor()?->encode(),
                    'previous_cursor' => $resource->previousCursor()?->encode(),
                    'has_more' => $resource->hasMorePages(),
                ];
                $data[$key] = $items;

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'meta' => $meta,
                ]);
            }
            $data[$key] = $paginator;

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        if ($paginator instanceof LengthAwarePaginator) {
            $data[$key] = $paginator->items();
            $meta = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];
        } elseif ($paginator instanceof CursorPaginator) {
            $data[$key] = $paginator->items();
            $meta = [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'previous_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ];
        } else {
            $data[$key] = $paginator;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        ]);
    }
}
