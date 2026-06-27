<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            // id da fakestoreapi (cart.id) como PK -> upsert idempotente.
            $table->unsignedBigInteger('id')->primary();

            // afiliado dono do pedido (cart.userId -> affiliates.id)
            $table->unsignedBigInteger('affiliate_id');

            // máquina de estados: pending -> approved/cancelled, approved -> refunded
            $table->string('status')->default('pending');

            // valor total do pedido, calculado na importação (decimal pra dinheiro)
            $table->decimal('total_value', 12, 2)->default(0);

            // data do pedido vinda da API (cart.date)
            $table->timestamp('ordered_at')->nullable();

            $table->timestamps();
            $table->softDeletes(); // exigido pelo desafio: deleted_at

            // FK com cascade: se o afiliado for removido, os pedidos vão junto
            $table->foreign('affiliate_id')
                ->references('id')->on('affiliates')
                ->cascadeOnDelete();

            // índice composto para o padrão de busca previsto no desafio:
            // filtragem por affiliate_id + status + created_at
            $table->index(['affiliate_id', 'status', 'created_at'], 'idx_orders_affiliate_status_created');

            // índices de apoio para os outros filtros do endpoint de listagem
            $table->index('status');
            $table->index('ordered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};