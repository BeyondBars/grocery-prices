<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('store_location')->nullable();
            $table->dateTime('purchased_at')->nullable();
            $table->string('receipt_number')->nullable();
            $table->integer('subtotal_cents')->nullable();
            $table->integer('discount_cents')->default(0);
            $table->integer('total_cents')->nullable();
            $table->char('currency', 3)->default('AUD');
            $table->string('status', 16)->index();

            // The original upload (on a private disk).
            $table->string('file_path')->nullable();
            $table->string('file_mime', 64)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->char('file_hash', 64)->nullable();

            // OCR output is kept so a receipt can be re-parsed without paying for OCR again.
            $table->string('ocr_provider', 32)->nullable();
            $table->longText('ocr_text')->nullable();
            $table->json('parsed_data')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'file_hash']);
            $table->index(['user_id', 'purchased_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
