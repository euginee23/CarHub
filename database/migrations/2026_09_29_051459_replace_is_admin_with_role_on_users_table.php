<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the admin flag with a role, so renters, owners, and administrators
     * are distinct kinds of account.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('renter')->after('password')->index();
        });

        // Existing accounts: admins stay admins; anyone verified as, or applying to
        // be, an owner becomes an owner; everyone else is a renter.
        DB::table('users')->where('is_admin', true)->update(['role' => 'admin']);

        DB::table('users')
            ->where('is_admin', false)
            ->where(fn ($query) => $query
                ->whereNotNull('owner_verified_at')
                ->orWhereIn('id', DB::table('owner_applications')->select('user_id')))
            ->update(['role' => 'owner']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        DB::table('users')->where('role', 'admin')->update(['is_admin' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
