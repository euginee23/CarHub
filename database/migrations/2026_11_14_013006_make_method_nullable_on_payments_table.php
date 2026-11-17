<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. The renter now picks how to pay on the gateway's checkout
     * page, so a payment's method is only known once it has been paid.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('payments')->whereNull('method')->update(['method' => 'qrph']);

        Schema::table('payments', function (Blueprint $table) {
            $table->string('method')->nullable(false)->change();
        });
    }
};
