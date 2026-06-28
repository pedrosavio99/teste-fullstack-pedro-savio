<?php

use App\Models\Affiliate;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// respostas falsas da fakestoreapi, controladas pelo teste
function fakeFakeStore(): void
{
    Http::fake([
        '*/users' => Http::response([
            ['id' => 1, 'email' => 'a@x.com', 'username' => 'ana',
             'name' => ['firstname' => 'Ana', 'lastname' => 'Silva'], 'phone' => '123'],
            ['id' => 2, 'email' => 'b@x.com', 'username' => 'bob',
             'name' => ['firstname' => 'Bob', 'lastname' => 'Souza'], 'phone' => '456'],
        ], 200),

        '*/products' => Http::response([
            ['id' => 10, 'title' => 'Produto A', 'price' => 50.00, 'category' => 'cat', 'description' => 'x', 'image' => ''],
            ['id' => 20, 'title' => 'Produto B', 'price' => 30.00, 'category' => 'cat', 'description' => 'y', 'image' => ''],
        ], 200),

        '*/carts' => Http::response([
            ['id' => 1, 'userId' => 1, 'date' => '2024-01-10',
             'products' => [['productId' => 10, 'quantity' => 2], ['productId' => 20, 'quantity' => 1]]],
            ['id' => 2, 'userId' => 2, 'date' => '2024-01-11',
             'products' => [['productId' => 10, 'quantity' => 1]]],
        ], 200),
    ]);
}

it('importa afiliados, produtos e pedidos pelo comando orders:sync', function () {
    fakeFakeStore();

    $this->artisan('orders:sync')->assertExitCode(0);

    // afiliados importados
    expect(Affiliate::count())->toBe(2);
    expect(Affiliate::find(1)->name)->toBe('Ana Silva');

    // produtos importados
    expect(Product::count())->toBe(2);

    // pedidos importados
    expect(Order::count())->toBe(2);

    // itens do pedido 1 (2 itens) e do pedido 2 (1 item)
    expect(OrderItem::count())->toBe(3);

    // valor total do pedido 1 = 2*50 + 1*30 = 130
    expect((float) Order::find(1)->total_value)->toBe(130.0);

    // todo pedido novo começa como pending
    expect(Order::find(1)->status)->toBe('pending');
});

it('é idempotente: rodar duas vezes não duplica dados', function () {
    fakeFakeStore();

    $this->artisan('orders:sync')->assertExitCode(0);
    $this->artisan('orders:sync')->assertExitCode(0);

    // mesmos números após a segunda execução
    expect(Affiliate::count())->toBe(2);
    expect(Product::count())->toBe(2);
    expect(Order::count())->toBe(2);
    expect(OrderItem::count())->toBe(3);
});

it('preserva o status de um pedido ao re-sincronizar', function () {
    fakeFakeStore();

    $this->artisan('orders:sync')->assertExitCode(0);

    // muda o status do pedido 1 para approved
    Order::find(1)->update(['status' => 'approved']);

    // re-sincroniza
    $this->artisan('orders:sync')->assertExitCode(0);

    // o status NÃO pode ter voltado para pending (upsert preserva o status)
    expect(Order::find(1)->status)->toBe('approved');
});