<?php

namespace App\Services;

use App\Events\OrderStatusChanged;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderService
{
    // Máquina de estados: cada chave aponta para os status válidos seguintes.
    private const TRANSITIONS = [
        'pending'   => ['approved', 'cancelled'],
        'approved'  => ['refunded'],
        'cancelled' => [],
        'refunded'  => [],
    ];

    public function __construct(
        private readonly OrderRepository $orders,
    ) {}

    public function list(array $filters)
    {
        return $this->orders->paginate($filters);
    }

    public function find(int $id): ?Order
    {
        return $this->orders->findWithDetails($id);
    }

    public function findOrder(int $id): ?Order
    {
        return $this->orders->findById($id);
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function allowedTransitions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    /**
     * Muda o status respeitando a máquina de estados, grava a auditoria,
     * invalida o cache de métricas e dispara o evento de mudança.
     *
     * @throws InvalidStatusTransitionException
     */
    public function changeStatus(Order $order, string $newStatus, ?int $userId = null): Order
    {
        $current = $order->status;

        if (! $this->canTransition($current, $newStatus)) {
            throw new InvalidStatusTransitionException($current, $newStatus);
        }

        DB::transaction(function () use ($order, $current, $newStatus, $userId) {
            $order->update(['status' => $newStatus]);

            OrderStatusLog::create([
                'order_id'    => $order->id,
                'from_status' => $current,
                'to_status'   => $newStatus,
                'changed_by'  => $userId,
                'changed_at'  => now(),
            ]);
        });

        // invalida o cache de métricas, já que os números mudaram
        $this->forgetMetricsCache();

        $order->refresh();

        // dispara o evento DEPOIS do commit: o listener enfileira o job
        // que envia o webhook ao N8N. Falha no envio não afeta esta operação.
        OrderStatusChanged::dispatch($order, $current, $newStatus);

        return $order;
    }

    public function forgetMetricsCache(): void
    {
        Cache::store('redis')->forget('orders:metrics');
    }

    /**
     * Métricas agregadas com cache de 5 minutos no Redis.
     */
    public function metrics(): array
    {
        return Cache::store('redis')->remember('orders:metrics', now()->addMinutes(5), function () {
            return array_merge(
                $this->orders->metrics(),
                ['cached_at' => now()->toIso8601String()]
            );
        });
    }

    /**
     * Resumo do afiliado: total de pedidos, receita, ticket médio
     * e taxa de cancelamento.
     */
    public function affiliateSummary(int $affiliateId): array
    {
        return $this->orders->affiliateSummary($affiliateId);
    }
}