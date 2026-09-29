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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('renter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->dateTime('pickup_at');
            $table->dateTime('return_at');
            $table->string('pickup_location');
            $table->unsignedInteger('daily_rate');
            $table->unsignedSmallInteger('days');
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('service_fee');
            $table->unsignedInteger('total');
            $table->string('status')->index();
            $table->text('renter_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('terms_version_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->string('terms_accepted_ip', 45)->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status', 'pickup_at', 'return_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
