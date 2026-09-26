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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('address')->nullable()->after('phone');
            $table->date('birthdate')->nullable()->after('address');
            $table->boolean('is_admin')->default(false)->after('password');
            $table->timestamp('owner_verified_at')->nullable()->after('is_admin');
            $table->timestamp('suspended_at')->nullable()->after('owner_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'birthdate', 'is_admin', 'owner_verified_at', 'suspended_at']);
        });
    }
};
