<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\FakeStoreClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function handle(FakeStoreClient $client): void
    {
        $products = $client->getProducts();

        $rows = [];
        foreach ($products as $p) {
            $rows[] = [
                'id'          => $p['id'],
                'title'       => $p['title'] ?? 'Produto ' . $p['id'],
                'price'       => $p['price'] ?? 0,
                'category'    => $p['category'] ?? null,
                'description' => $p['description'] ?? null,
                'image'       => $p['image'] ?? null,
            ];
        }

        if (! empty($rows)) {
            Product::upsert(
                $rows,
                ['id'],
                ['title', 'price', 'category', 'description', 'image']
            );
        }
    }
}