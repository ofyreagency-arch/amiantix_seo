<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_sites', function (Blueprint $table): void {
            $table->string('preset', 80)->default('generic')->after('locale');
        });

        DB::table('seo_sites')
            ->where('niche', 'amiante')
            ->update(['preset' => 'amiantix']);
    }

    public function down(): void
    {
        Schema::table('seo_sites', function (Blueprint $table): void {
            $table->dropColumn('preset');
        });
    }
};
