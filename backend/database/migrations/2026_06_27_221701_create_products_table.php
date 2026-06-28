<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            // id da fakestoreapi (product.id) como PK, mesmo motivo dos affiliates:
            // upsert idempotente na importação.
            $table->unsignedBigInteger('id')->primary();
            $table->string('title');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->timestamps();

            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};