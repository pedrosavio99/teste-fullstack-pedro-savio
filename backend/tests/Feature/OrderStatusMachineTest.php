<?php

use App\Models\Affiliate;
use App\Models\Order;
use App\Models\OrderStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// helper: cria um afiliado e um pedido num status inicial
function makeOrder(string $status = 'pending'): Order
{
    $affiliate = Affiliate::create([
        'id' => 1, 'name' => 'Afiliado Teste', 'email' => 'a@x.com', 'status' => 'active',
    ]);

    return Order::create([
        'id' => 100, 'affiliate_id' => $affiliate->id, 'status' => $status,
        'total_value' => 100.00, 'ordered_at' => now(),
    ]);
}

it('permite transição válida de pending para approved', function () {
    $order = makeOrder('pending');

    $response = $this->postJson("/api/orders/{$order->id}/status", ['status' => 'approved']);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'approved');
    expect($order->fresh()->status)->toBe('approved');
});

it('permite transição válida de pending para cancelled', function () {
    $order = makeOrder('pending');

    $response = $this->postJson("/api/orders/{$order->id}/status", ['status' => 'cancelled']);

    $response->assertOk();
    expect($order->fresh()->status)->toBe('cancelled');
});

it('permite transição válida de approved para refunded', function () {
    $order = makeOrder('approved');

    $response = $this->postJson("/api/orders/{$order->id}/status", ['status' => 'refunded']);

    $response->assertOk();
    expect($order->fresh()->status)->toBe('refunded');
});

it('rejeita transição inválida de pending para refunded com 422', function () {
    $order = makeOrder('pending');

    $response = $this->postJson("/api/orders/{$order->id}/status", ['status' => 'refunded']);

    $response->assertStatus(422);
    // o status não pode ter mudado
    expect($order->fresh()->status)->toBe('pending');
});

it('rejeita transição a partir de estado terminal (cancelled) com 422', function () {
    $order = makeOrder('cancelled');

    $response = $this->postJson("/api/orders/{$order->id}/status", ['status' => 'approved']);

    $response->assertStatus(422);
    expect($order->fresh()->status)->toBe('cancelled');
});

it('registra um log de auditoria a cada transição válida', function () {
    $order = makeOrder('pending');

    $this->postJson("/api/orders/{$order->id}/status", ['status' => 'approved']);

    // deve existir um log de pending -> approved
    $log = OrderStatusLog::where('order_id', $order->id)
        ->where('to_status', 'approved')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->from_status)->toBe('pending');
});

it('valida que o status enviado é um dos permitidos', function () {
    $order = makeOrder('pending');

    $response = $this->postJson("/api/orders/{$order->id}/status", ['status' => 'inexistente']);

    // o Form Request barra status fora da lista
    $response->assertStatus(422);
});