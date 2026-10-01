<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let administrators record why an account was suspended or a listing
     * was taken down.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('suspension_reason')->nullable()->after('suspended_at');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->timestamp('moderated_at')->nullable()->after('status');
            $table->text('moderation_reason')->nullable()->after('moderated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('suspension_reason');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['moderated_at', 'moderation_reason']);
        });
    }
};
