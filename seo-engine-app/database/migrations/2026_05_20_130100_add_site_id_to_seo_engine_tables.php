<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // seo_vectors: drop old unique (entity_type, entity_key), add site_id, new unique (site_id, entity_type, entity_key)
        Schema::table('seo_vectors', function (Blueprint $table): void {
            $table->string('site_id')->default('default')->after('id');
            $table->dropUnique(['entity_type', 'entity_key']);
            $table->unique(['site_id', 'entity_type', 'entity_key']);
            $table->index('site_id');
        });

        // seo_semantic_links: add site_id + compound index
        Schema::table('seo_semantic_links', function (Blueprint $table): void {
            $table->string('site_id')->default('default')->after('id');
            $table->index(['site_id', 'relation_type', 'source_key']);
        });

        // seo_search_console_metrics: add site_id
        Schema::table('seo_search_console_metrics', function (Blueprint $table): void {
            $table->string('site_id')->default('default')->after('id');
            $table->index(['site_id', 'metric_date', 'window_days']);
        });
    }

    public function down(): void
    {
        Schema::table('seo_vectors', function (Blueprint $table): void {
            $table->dropIndex(['site_id']);
            $table->dropUnique(['site_id', 'entity_type', 'entity_key']);
            $table->unique(['entity_type', 'entity_key']);
            $table->dropColumn('site_id');
        });

        Schema::table('seo_semantic_links', function (Blueprint $table): void {
            $table->dropIndex(['site_id', 'relation_type', 'source_key']);
            $table->dropColumn('site_id');
        });

        Schema::table('seo_search_console_metrics', function (Blueprint $table): void {
            $table->dropIndex(['site_id', 'metric_date', 'window_days']);
            $table->dropColumn('site_id');
        });
    }
};
