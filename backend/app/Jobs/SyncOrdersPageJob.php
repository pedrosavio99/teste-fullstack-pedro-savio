<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Services\FakeStoreClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncOrdersPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5; // 5s entre tentativas

    public function __construct(
        public int $page,
        public int $perPage,
    ) {}

    public function handle(FakeStoreClient $client): void
    {
        // Busca todos os carts e fatia a página deste job (offset/limit).
        // Numa API paginada de verdade, aqui chamaríamos getCarts($page, $perPage).
        $allCarts = $client->getCarts();
        $offset   = ($this->page - 1) * $this->perPage;
        $carts    = array_slice($allCarts, $offset, $this->perPage);

        foreach ($carts as $cart) {
            $this->importCart($cart);
        }
    }

    private function importCart(array $cart): void
    {
        $orderId     = $cart['id'];
        $affiliateId = $cart['userId'] ?? null;
        $products    = $cart['products'] ?? [];

        if (! $affiliateId || empty($products)) {
            return; // cart inválido, ignora
        }

        // valor total = soma de (quantidade * preço) dos itens.
        // o preço vem da tabela products (já importada na fase anterior).
        $productIds = array_column($products, 'productId');
        $prices = \App\Models\Product::whereIn('id', $productIds)
            ->pluck('price', 'id'); // [productId => price]

        $total = 0;
        foreach ($products as $item) {
            $pid   = $item['productId'];
            $qty   = $item['quantity'] ?? 1;
            $price = (float) ($prices[$pid] ?? 0);
            $total += $qty * $price;
        }

        // Tudo numa transação: ou grava pedido + itens + log juntos, ou nada.
        // Garante consistência se algo falhar no meio.
        DB::transaction(function () use ($cart, $orderId, $affiliateId, $products, $prices, $total) {
            // pedido novo? guardamos pra criar o log inicial só na primeira vez
            $isNew = ! Order::whereKey($orderId)->exists();

            Order::upsert([[
                'id'           => $orderId,
                'affiliate_id' => $affiliateId,
                'status'       => 'pending',
                'total_value'  => $total,
                'ordered_at'   => isset($cart['date']) ? date('Y-m-d H:i:s', strtotime($cart['date'])) : now(),
            ]], ['id'], ['affiliate_id', 'total_value', 'ordered_at']);
            // nota: NÃO atualizamos 'status' no upsert, pra não sobrescrever
            // uma mudança de status feita depois pela API ao re-sincronizar.

            // itens do pedido (upsert idempotente pela combinação order+product)
            $itemRows = [];
            foreach ($products as $item) {
                $pid = $item['productId'];
                $itemRows[] = [
                    'order_id'   => $orderId,
                    'product_id' => $pid,
                    'quantity'   => $item['quantity'] ?? 1,
                    'price'      => (float) ($prices[$pid] ?? 0),
                ];
            }
            if (! empty($itemRows)) {
                OrderItem::upsert($itemRows, ['order_id', 'product_id'], ['quantity', 'price']);
            }

            // log inicial de status só na criação do pedido (auditoria)
            if ($isNew) {
                OrderStatusLog::create([
                    'order_id'    => $orderId,
                    'from_status' => null,
                    'to_status'   => 'pending',
                    'changed_by'  => null, // importação automática, sem usuário
                    'changed_at'  => now(),
                ]);
            }
        });
    }
}