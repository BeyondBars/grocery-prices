<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each line keeps what was printed on the receipt; it is never rewritten from the product.
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('original_name');
            $table->string('normalized_name')->nullable();
            $table->string('sku', 32)->nullable();
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit', 8);
            $table->integer('unit_price_cents')->nullable();
            $table->integer('total_price_cents');
            $table->integer('discount_cents')->default(0);

            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('suggested_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('match_status', 16)->index();
            $table->unsignedTinyInteger('match_confidence')->nullable();
            $table->string('matched_by', 16)->nullable();
            $table->foreignId('matched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();

            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
    }
};
