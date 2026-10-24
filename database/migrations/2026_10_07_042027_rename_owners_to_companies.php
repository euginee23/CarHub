<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables whose vehicle-owner column becomes a rental-company column.
     *
     * @var array<int, string>
     */
    private array $ownedTables = ['vehicles', 'bookings', 'reviews'];

    /**
     * CarHub lists vehicles for rental companies rather than private owners:
     * rename the owner role, columns, and application table, give companies a
     * name and address, and repoint the class names and keys already stored.
     */
    public function up(): void
    {
        foreach ($this->ownedTables as $table) {
            $this->renameUserForeignKey($table, 'owner_id', 'company_id');
        }

        $this->renameApplicationsTable('owner_applications', 'company_applications');

        Schema::table('company_applications', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('status');
            $table->string('business_address')->nullable()->after('company_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('owner_verified_at', 'company_verified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('company_verified_at');
            $table->string('company_address')->nullable()->after('company_name');
        });

        DB::table('users')->where('role', 'owner')->update(['role' => 'company']);

        $this->replaceStoredNames([
            'App\\Models\\OwnerApplication' => 'App\\Models\\CompanyApplication',
            'App\\Notifications\\OwnerApplicationReviewed' => 'App\\Notifications\\CompanyApplicationReviewed',
            'owner_application' => 'company_application',
            '/owner/' => '/company/',
            '\\/owner\\/' => '\\/company\\/',
            '/owner"' => '/company"',
            '\\/owner"' => '\\/company"',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceStoredNames([
            'App\\Models\\CompanyApplication' => 'App\\Models\\OwnerApplication',
            'App\\Notifications\\CompanyApplicationReviewed' => 'App\\Notifications\\OwnerApplicationReviewed',
            'company_application' => 'owner_application',
            '/company/' => '/owner/',
            '\\/company\\/' => '\\/owner\\/',
            '/company"' => '/owner"',
            '\\/company"' => '\\/owner"',
        ]);

        DB::table('users')->where('role', 'company')->update(['role' => 'owner']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'company_address']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('company_verified_at', 'owner_verified_at');
        });

        Schema::table('company_applications', function (Blueprint $table) {
            $table->dropColumn(['company_name', 'business_address']);
        });

        $this->renameApplicationsTable('company_applications', 'owner_applications');

        foreach ($this->ownedTables as $table) {
            $this->renameUserForeignKey($table, 'company_id', 'owner_id');
        }
    }

    /**
     * Rename a cascading foreign key to users, keeping the constraint and index
     * names in step with the column. SQLite carries the reference across a
     * column rename by itself.
     */
    private function renameUserForeignKey(string $table, string $from, string $to): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from, $to));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $from) {
            $blueprint->dropForeign([$from]);
            $blueprint->dropIndex("{$table}_{$from}_foreign");
        });

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->renameColumn($from, $to));

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign($to)->references('id')->on('users')->cascadeOnDelete());
    }

    /**
     * Rename the applications table along with its constraint and index names,
     * which are derived from the table name.
     */
    private function renameApplicationsTable(string $from, string $to): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if (! $sqlite) {
            Schema::table($from, function (Blueprint $table) use ($from) {
                $table->dropForeign(['user_id']);
                $table->dropForeign(['reviewed_by']);
                $table->dropIndex("{$from}_user_id_foreign");
                $table->dropIndex("{$from}_reviewed_by_foreign");
            });
        }

        Schema::rename($from, $to);

        Schema::table($to, function (Blueprint $table) use ($from, $to, $sqlite) {
            $table->renameIndex("{$from}_status_index", "{$to}_status_index");

            if (! $sqlite) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Rewrite class names, activity actions, notification keys, and notification
     * links that were stored under the old names.
     *
     * @param  array<string, string>  $replacements
     */
    private function replaceStoredNames(array $replacements): void
    {
        $columns = [
            'verification_documents' => ['documentable_type'],
            'notifications' => ['type', 'data'],
            'activity_logs' => ['subject_type', 'action'],
        ];

        foreach ($columns as $table => $tableColumns) {
            foreach ($tableColumns as $column) {
                foreach ($replacements as $search => $replace) {
                    DB::update("update {$table} set {$column} = replace({$column}, ?, ?)", [$search, $replace]);
                }
            }
        }
    }
};
