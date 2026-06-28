<?php

use App\Models\Affiliate;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function seedOrders(): void
{
    Affiliate::create(['id' => 1, 'name' => 'A1', 'status' => 'active']);

    Order::create(['id' => 1, 'affiliate_id' => 1, 'status' => 'approved',  'total_value' => 100, 'ordered_at' => now()]);
    Order::create(['id' => 2, 'affiliate_id' => 1, 'status' => 'approved',  'total_value' => 200, 'ordered_at' => now()]);
    Order::create(['id' => 3, 'affiliate_id' => 1, 'status' => 'pending',   'total_value' => 50,  'ordered_at' => now()]);
    Order::create(['id' => 4, 'affiliate_id' => 1, 'status' => 'cancelled', 'total_value' => 30,  'ordered_at' => now()]);
}

it('calcula as métricas corretamente', function () {
    seedOrders();

    $response = $this->getJson('/api/orders/metrics');

    $response->assertOk();
    $response->assertJsonPath('data.total_orders', 4);
    $response->assertJsonPath('data.orders_by_status.approved', 2);
    $response->assertJsonPath('data.orders_by_status.pending', 1);
    $response->assertJsonPath('data.orders_by_status.cancelled', 1);
    $response->assertJsonPath('data.revenue', 300);
});

it('serve as métricas do cache na segunda chamada', function () {
    seedOrders();

    $this->getJson('/api/orders/metrics')->assertOk();

    expect(Cache::get('orders:metrics'))->not->toBeNull();

    // cria um pedido novo SEM passar pela API (não invalida o cache)
    Order::create(['id' => 99, 'affiliate_id' => 1, 'status' => 'approved', 'total_value' => 999, 'ordered_at' => now()]);

    // segunda chamada deve vir do cache, ignorando o pedido novo
    $response = $this->getJson('/api/orders/metrics');
    $response->assertJsonPath('data.total_orders', 4); // ainda 4, não 5
});

it('invalida o cache ao mudar um status', function () {
    seedOrders();

    $this->getJson('/api/orders/metrics')->assertOk();
    expect(Cache::get('orders:metrics'))->not->toBeNull();

    // muda um status pela API (deve invalidar o cache)
    $this->postJson('/api/orders/3/status', ['status' => 'approved'])->assertOk();

    // o cache foi limpo
    expect(Cache::get('orders:metrics'))->toBeNull();

    // nova chamada recalcula: agora 3 approved
    $response = $this->getJson('/api/orders/metrics');
    $response->assertJsonPath('data.orders_by_status.approved', 3);
});