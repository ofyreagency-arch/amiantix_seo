<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('remote_installations') || ! Schema::hasColumn('remote_installations', 'encrypted_credentials')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE remote_installations MODIFY encrypted_credentials LONGTEXT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE remote_installations ALTER COLUMN encrypted_credentials TYPE TEXT USING encrypted_credentials::text');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('remote_installations') || ! Schema::hasColumn('remote_installations', 'encrypted_credentials')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE remote_installations MODIFY encrypted_credentials JSON NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE remote_installations ALTER COLUMN encrypted_credentials TYPE JSON USING encrypted_credentials::json');
        }
    }
};
