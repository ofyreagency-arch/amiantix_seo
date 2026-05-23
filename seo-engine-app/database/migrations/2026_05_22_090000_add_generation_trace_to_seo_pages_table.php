<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('seo_pages', 'generation_source')) {
                $table->string('generation_source', 32)->nullable()->after('last_observed_at');
            }

            if (! Schema::hasColumn('seo_pages', 'generation_error')) {
                $table->text('generation_error')->nullable()->after('generation_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('seo_pages', 'generation_error')) {
                $table->dropColumn('generation_error');
            }

            if (Schema::hasColumn('seo_pages', 'generation_source')) {
                $table->dropColumn('generation_source');
            }
        });
    }
};
