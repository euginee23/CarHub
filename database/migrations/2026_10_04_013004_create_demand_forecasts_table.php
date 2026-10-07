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
        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_type');
            $table->date('date');
            $table->decimal('predicted_requests', 8, 3);
            $table->string('method');
            $table->string('run_id')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['vehicle_type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_forecasts');
    }
};
