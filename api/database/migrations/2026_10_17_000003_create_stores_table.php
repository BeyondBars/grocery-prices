<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A store is a chain (Woolworths, Coles). The branch is kept as text on each receipt.
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            // ABN printed on the receipt: the most reliable way to identify the chain.
            $table->string('abn', 11)->nullable()->index();
            // Header text that identifies the store on a receipt, e.g. ["WOOLWORTHS", "WOOLIES"].
            $table->json('aliases')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
