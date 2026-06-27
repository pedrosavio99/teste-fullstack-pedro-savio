<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); // aqui um id próprio autoincrementado faz sentido

            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');

            $table->unsignedInteger('quantity')->default(1);

            // preço do produto no momento do pedido (snapshot), decimal pra dinheiro
            $table->decimal('price', 10, 2)->default(0);

            $table->timestamps();

            // se o pedido for removido, os itens vão junto
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->cascadeOnDelete();

            // se o produto for removido, removemos o item também
            $table->foreign('product_id')
                ->references('id')->on('products')
                ->cascadeOnDelete();

            // evita duplicar o mesmo produto no mesmo pedido (ajuda o upsert)
            $table->unique(['order_id', 'product_id'], 'uq_order_product');

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};