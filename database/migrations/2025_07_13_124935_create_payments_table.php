<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('payment_id')->unique(); // PayPal payment ID
            $table->string('payer_id')->nullable(); // PayPal payer ID
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('payment_method')->default('paypal');
            $table->json('payment_details')->nullable(); // Store PayPal response
            $table->timestamp('paid_at')->nullable();
            $table->integer('additional_storage_mb')->default(0); // Additional storage purchased
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
