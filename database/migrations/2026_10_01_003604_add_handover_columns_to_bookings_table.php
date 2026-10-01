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
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('picked_up_at')->nullable()->after('payment_due_at');
            $table->unsignedInteger('pickup_odometer')->nullable()->after('picked_up_at');
            $table->string('pickup_fuel')->nullable()->after('pickup_odometer');
            $table->text('pickup_notes')->nullable()->after('pickup_fuel');
            $table->timestamp('returned_at')->nullable()->after('pickup_notes');
            $table->unsignedInteger('return_odometer')->nullable()->after('returned_at');
            $table->string('return_fuel')->nullable()->after('return_odometer');
            $table->text('return_notes')->nullable()->after('return_fuel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['picked_up_at', 'pickup_odometer', 'pickup_fuel', 'pickup_notes', 'returned_at', 'return_odometer', 'return_fuel', 'return_notes']);
        });
    }
};
