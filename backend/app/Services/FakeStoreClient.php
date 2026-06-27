<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Client\Response;

class FakeStoreClient
{
    private string $baseUrl;

    // no máximo N chamadas por janela de 1 segundo (rate limiting)
    private int $maxPerSecond = 5;

    public function __construct()
    {
        // a URL base vem do .env, nunca hardcoded
        $this->baseUrl = rtrim(config('services.fakestore.url', 'https://fakestoreapi.com'), '/');
    }

    public function getCarts(): array
    {
        return $this->get('/carts');
    }

    public function getUsers(): array
    {
        return $this->get('/users');
    }

    public function getProducts(): array
    {
        return $this->get('/products');
    }

    /**
     * Faz um GET respeitando o rate limit. Se estourar a janela,
     * espera e tenta de novo, em vez de falhar na hora.
     */
    private function get(string $path): array
    {
        $key = 'fakestore-http';

        // espera ativa curta até liberar uma vaga na janela de rate limit
        while (RateLimiter::tooManyAttempts($key, $this->maxPerSecond)) {
            usleep(200_000); // 200ms
        }
        RateLimiter::hit($key, 1); // registra a chamada nesta janela de 1s

        $response = Http::timeout(15)
            ->retry(3, 500) // 3 tentativas no nível HTTP, 500ms entre elas
            ->acceptJson()
            ->get($this->baseUrl . $path);

        $response->throw(); // lança exceção se vier erro HTTP (4xx/5xx)

        return $response->json() ?? [];
    }
}