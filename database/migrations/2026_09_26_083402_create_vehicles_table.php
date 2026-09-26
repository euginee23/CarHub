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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year');
            $table->string('type')->index();
            $table->string('transmission');
            $table->string('fuel');
            $table->unsignedTinyInteger('seats');
            $table->unsignedInteger('price_per_day');
            $table->text('description');
            $table->json('features');
            $table->string('location');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('instant_book')->default(false);
            $table->boolean('featured')->default(false);
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('trips_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
