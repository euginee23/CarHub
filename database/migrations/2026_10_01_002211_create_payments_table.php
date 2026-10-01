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
            $table->string('reference')->unique();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->unsignedBigInteger('amount')->comment('In centavos.');
            $table->string('currency', 3)->default('PHP');
            $table->string('status')->index();
            $table->string('provider');
            $table->string('provider_checkout_id')->nullable()->unique();
            $table->string('provider_payment_intent_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
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
