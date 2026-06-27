<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListOrdersRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Exceptions\InvalidStatusTransitionException;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $service,
    ) {}

    // GET /api/orders — lista paginada com filtros e ordenação
    public function index(ListOrdersRequest $request): JsonResponse
    {
        $paginator = $this->service->list($request->filters());

        return ApiResponse::paginated($paginator);
    }

    // GET /api/orders/metrics — métricas agregadas com cache de 5 min
    public function metrics(): JsonResponse
    {
        $metrics = $this->service->metrics();

        return ApiResponse::success($metrics, [
            'cached_at' => $metrics['cached_at'] ?? null,
        ]);
    }

    // GET /api/orders/{id} — detalhe com itens e histórico de status
    public function show(int $id): JsonResponse
    {
        $order = $this->service->find($id);

        if (! $order) {
            return ApiResponse::error('Pedido não encontrado.', 404);
        }

        return ApiResponse::success($order);
    }

    // POST /api/orders/{id}/status — muda o status via máquina de estados
    public function updateStatus(UpdateOrderStatusRequest $request, int $id): JsonResponse
    {
        $order = $this->service->findOrder($id);

        if (! $order) {
            return ApiResponse::error('Pedido não encontrado.', 404);
        }

        try {
            $userId = $request->integer('changed_by') ?: null;

            $updated = $this->service->changeStatus($order, $request->validated()['status'], $userId);

            return ApiResponse::success([
                'id'     => $updated->id,
                'status' => $updated->status,
            ]);
        } catch (InvalidStatusTransitionException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}