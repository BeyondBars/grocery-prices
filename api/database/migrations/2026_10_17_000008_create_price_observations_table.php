<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Price history. Receipt prices are derived from receipt_items and rebuilt when a match changes.
        Schema::create('price_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('source', 16);
            $table->foreignId('receipt_item_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Price actually paid per unit (each or per kg), after discounts.
            $table->integer('price_cents');
            $table->integer('regular_price_cents')->nullable();
            $table->boolean('is_promotion')->default(false);
            $table->string('unit', 8);
            $table->date('observed_on');
            $table->date('valid_until')->nullable();
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'store_id', 'observed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_observations');
    }
};
