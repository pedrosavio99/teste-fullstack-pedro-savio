<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendOrderWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    // mapeia o novo status para o caminho do webhook no N8N.
    // status sem workflow dedicado não disparam (ex.: refunded).
    private const STATUS_PATHS = [
        'approved'  => 'order-approved',
        'cancelled' => 'order-cancelled',
    ];

    public function __construct(
        public int $orderId,
        public int $affiliateId,
        public string $previousStatus,
        public string $newStatus,
        public float $totalValue,
    ) {}

    /**
     * Backoff exponencial: 10s, 20s, 40s entre tentativas.
     */
    public function backoff(): array
    {
        return [10, 20, 40];
    }

    public function handle(): void
    {
        $path = self::STATUS_PATHS[$this->newStatus] ?? null;

        // status sem workflow dedicado: nada a fazer, sai sem erro.
        if ($path === null) {
            return;
        }

        $base = rtrim((string) config('services.n8n.webhook_url'), '/');

        if ($base === '') {
            Log::warning('N8N_WEBHOOK_URL não configurada; webhook não enviado.', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        $url = "{$base}/webhook/{$path}";

        $payload = [
            'event'           => 'order.status_changed',
            'order_id'        => $this->orderId,
            'affiliate_id'    => $this->affiliateId,
            'previous_status' => $this->previousStatus,
            'new_status'      => $this->newStatus,
            'total_value'     => $this->totalValue,
            'occurred_at'     => now()->toIso8601String(),
        ];

        $response = Http::timeout(10)->acceptJson()->post($url, $payload);

        // 4xx/5xx aciona o retry com backoff.
        $response->throw();
    }
}