<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * Resposta de sucesso padronizada: data, meta, errors.
     */
    public static function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'data'   => $data,
            'meta'   => $meta ?: null,
            'errors' => null,
        ], $status);
    }

    /**
     * Resposta de erro padronizada.
     */
    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'data'   => null,
            'meta'   => null,
            'errors' => $errors ?: ['message' => $message],
        ], $status);
    }

    /**
     * Converte um paginator do Laravel para o formato padrão,
     * extraindo os metadados de paginação para 'meta'.
     */
    public static function paginated(LengthAwarePaginator $paginator, mixed $data = null): JsonResponse
    {
        return response()->json([
            'data' => $data ?? $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
            'errors' => null,
        ]);
    }
}