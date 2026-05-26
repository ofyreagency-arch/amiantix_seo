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
            if (! Schema::hasColumn('seo_pages', 'published_live')) {
                $table->boolean('published_live')->default(false)->after('published_at');
            }

            if (! Schema::hasColumn('seo_pages', 'published_live_at')) {
                $table->timestamp('published_live_at')->nullable()->after('published_live');
            }

            if (! Schema::hasColumn('seo_pages', 'live_url')) {
                $table->string('live_url')->nullable()->after('published_live_at');
            }

            if (! Schema::hasColumn('seo_pages', 'last_observed_at')) {
                $table->timestamp('last_observed_at')->nullable()->after('live_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('seo_pages', 'last_observed_at')) {
                $table->dropColumn('last_observed_at');
            }

            if (Schema::hasColumn('seo_pages', 'live_url')) {
                $table->dropColumn('live_url');
            }

            if (Schema::hasColumn('seo_pages', 'published_live_at')) {
                $table->dropColumn('published_live_at');
            }

            if (Schema::hasColumn('seo_pages', 'published_live')) {
                $table->dropColumn('published_live');
            }
        });
    }
};
