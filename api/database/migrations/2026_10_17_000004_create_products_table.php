<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('variant')->nullable();
            // Size in base units (g, ml or each): "2L" is 2000 ml.
            $table->decimal('size_amount', 10, 3)->nullable();
            $table->string('size_unit', 8)->nullable();
            $table->unsignedSmallInteger('pack_count')->default(1);
            $table->string('barcode', 14)->nullable()->unique();
            // Upper-cased brand + name + variant tokens, used to find match candidates.
            $table->string('match_key')->index();
            // Set when this product was a duplicate and merged into another one.
            $table->foreignId('merged_into_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
