<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Receipt wordings known to mean a product, e.g. "PAULS FC MILK 2L" at Woolworths.
        Schema::create('product_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('normalized_text');
            $table->string('sku', 32)->nullable();
            $table->string('source', 16);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('times_seen')->default(1);
            $table->timestamps();

            $table->unique(['store_id', 'normalized_text']);
            $table->index(['store_id', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_aliases');
    }
};
