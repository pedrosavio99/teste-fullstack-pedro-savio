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

    public function __construct(
        public int $orderId,
        public int $affiliateId,
        public string $previousStatus,
        public string $newStatus,
        public float $totalValue,
    ) {}

    /**
     * Backoff exponencial: espera 10s, depois 20s, depois 40s entre tentativas.
     */
    public function backoff(): array
    {
        return [10, 20, 40];
    }

    public function handle(): void
    {
        $url = config('services.n8n.webhook_url');

        // Sem URL configurada: não é erro, apenas não há para onde enviar.
        if (empty($url)) {
            Log::warning('N8N_WEBHOOK_URL não configurada; webhook não enviado.', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

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

        // Lança exceção em caso de 4xx/5xx para acionar o retry com backoff.
        $response->throw();
    }
}