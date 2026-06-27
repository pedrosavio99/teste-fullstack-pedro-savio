<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class OrderRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Order::query()->with('affiliate');

        $this->applyFilters($query, $filters);

        $sortBy  = $filters['sort_by'] ?? 'ordered_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage)->withQueryString();
    }

    public function findWithDetails(int $id): ?Order
    {
        return Order::query()
            ->with([
                'affiliate',
                'items.product',
                'statusLogs' => fn ($q) => $q->orderBy('changed_at', 'asc'),
                'statusLogs.changedBy:id,name',
            ])
            ->find($id);
    }

    public function findById(int $id): ?Order
    {
        return Order::find($id);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['affiliate_id'])) {
            $query->where('affiliate_id', $filters['affiliate_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('ordered_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('ordered_at', '<=', $filters['date_to']);
        }

        if (isset($filters['min_value'])) {
            $query->where('total_value', '>=', $filters['min_value']);
        }

        if (isset($filters['max_value'])) {
            $query->where('total_value', '<=', $filters['max_value']);
        }
    }

    public function metrics(): array
    {
        $byStatus = Order::query()
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(total_value),0) as revenue')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $statuses = ['pending', 'approved', 'cancelled', 'refunded'];
        $counts = [];
        $revenueByStatus = [];
        foreach ($statuses as $s) {
            $counts[$s] = (int) ($byStatus[$s]->total ?? 0);
            $revenueByStatus[$s] = (float) ($byStatus[$s]->revenue ?? 0);
        }

        $totalOrders = array_sum($counts);
        $revenue = $revenueByStatus['approved'] + $revenueByStatus['refunded'];
        $billed  = $counts['approved'] + $counts['refunded'];

        return [
            'total_orders'      => $totalOrders,
            'orders_by_status'  => $counts,
            'revenue'           => round($revenue, 2),
            'average_ticket'    => $billed > 0 ? round($revenue / $billed, 2) : 0,
            'cancellation_rate' => $totalOrders > 0
                ? round($counts['cancelled'] / $totalOrders * 100, 2)
                : 0,
        ];
    }

    public function affiliateSummary(int $affiliateId): array
    {
        $agg = Order::query()
            ->where('affiliate_id', $affiliateId)
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(total_value),0) as revenue')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn ($s) => (int) ($agg[$s]->total ?? 0);
        $rev   = fn ($s) => (float) ($agg[$s]->revenue ?? 0);

        $totalOrders = $count('pending') + $count('approved') + $count('cancelled') + $count('refunded');
        $revenue     = $rev('approved') + $rev('refunded');
        $billed      = $count('approved') + $count('refunded');

        return [
            'affiliate_id'      => $affiliateId,
            'total_orders'      => $totalOrders,
            'revenue'           => round($revenue, 2),
            'average_ticket'    => $billed > 0 ? round($revenue / $billed, 2) : 0,
            'cancellation_rate' => $totalOrders > 0
                ? round($count('cancelled') / $totalOrders * 100, 2)
                : 0,
        ];
    }
}