<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Receipt wordings a user marked "not a product" (bag fees, deposits), so they're skipped next time.
        Schema::create('ignored_wordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('normalized_text');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'normalized_text']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ignored_wordings');
    }
};
