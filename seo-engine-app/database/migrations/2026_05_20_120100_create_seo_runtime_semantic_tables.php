<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_search_console_metrics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_page_id')->nullable()->constrained('seo_pages')->nullOnDelete();
            $table->date('metric_date');
            $table->unsignedInteger('window_days')->default(28);
            $table->string('query')->nullable();
            $table->text('url')->nullable();
            $table->double('clicks')->default(0);
            $table->double('impressions')->default(0);
            $table->double('ctr')->default(0);
            $table->double('position')->default(0);
            $table->boolean('is_indexed')->nullable();
            $table->json('coverage_json')->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamps();

            $table->index(['metric_date', 'window_days']);
            $table->index(['seo_page_id', 'metric_date']);
        });

        Schema::create('seo_vectors', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type');
            $table->string('entity_key');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->longText('source_text');
            $table->string('source_hash', 40);
            $table->string('embedding_model');
            $table->string('embedding_version');
            $table->json('embedding_json');
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_key']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('seo_semantic_links', function (Blueprint $table): void {
            $table->id();
            $table->string('relation_type');
            $table->string('source_key');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('target_key');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('label');
            $table->text('url')->nullable();
            $table->string('reason')->nullable();
            $table->double('similarity_score')->default(0);
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['relation_type', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_semantic_links');
        Schema::dropIfExists('seo_vectors');
        Schema::dropIfExists('seo_search_console_metrics');
    }
};
