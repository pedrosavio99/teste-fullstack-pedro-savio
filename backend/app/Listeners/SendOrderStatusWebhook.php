<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Jobs\SendOrderWebhookJob;

class SendOrderStatusWebhook
{
    public function handle(OrderStatusChanged $event): void
    {
        // O listener apenas enfileira o job que faz o POST.
        // Assim o envio acontece em segundo plano e falhas não afetam
        // o fluxo principal de atualização do pedido.
        SendOrderWebhookJob::dispatch(
            orderId:        $event->order->id,
            affiliateId:    $event->order->affiliate_id,
            previousStatus: $event->previousStatus,
            newStatus:      $event->newStatus,
            totalValue:     (float) $event->order->total_value,
        );
    }
}