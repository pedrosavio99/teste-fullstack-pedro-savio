<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id');

            // auditoria: de qual status para qual status
            $table->string('from_status')->nullable(); // null na criação do pedido
            $table->string('to_status');

            // usuário responsável pela mudança (exigência do desafio).
            // nullable porque mudanças automáticas (importação) não têm usuário.
            $table->unsignedBigInteger('changed_by')->nullable();

            // timestamp da mudança (exigência do desafio)
            $table->timestamp('changed_at')->useCurrent();

            $table->timestamps();

            // se o pedido for removido, o histórico vai junto
            $table->foreign('order_id')
                ->references('id')->on('orders')
                ->cascadeOnDelete();

            // FK para o usuário responsável (tabela users padrão do Laravel).
            // nullOnDelete: se o usuário sumir, mantemos o log mas zeramos o autor.
            $table->foreign('changed_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            // busca do histórico de um pedido em ordem cronológica
            $table->index(['order_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};