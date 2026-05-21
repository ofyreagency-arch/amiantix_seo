<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_site_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedTinyInteger('health_score')->default(0);
            $table->unsignedSmallInteger('page_count')->default(0);
            $table->unsignedSmallInteger('published_count')->default(0);
            $table->decimal('avg_seo_score', 5, 2)->nullable();
            $table->decimal('avg_quality_score', 5, 2)->nullable();
            $table->decimal('avg_topical_score', 5, 2)->nullable();
            $table->date('snapshot_date');
            $table->timestamps();

            $table->index(['site_id', 'snapshot_date']);
        });

        Schema::create('seo_strategy_items', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedTinyInteger('priority')->default(5);
            $table->string('type')->default('page');
            $table->string('title');
            $table->text('description');
            $table->json('keywords_json')->nullable();
            $table->string('estimated_impact')->default('medium');
            $table->string('status')->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('seo_crawl_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->text('url');
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->boolean('is_covered')->default(false);
            $table->unsignedBigInteger('coverage_page_id')->nullable();
            $table->timestamp('crawled_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'is_covered']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_crawl_pages');
        Schema::dropIfExists('seo_strategy_items');
        Schema::dropIfExists('seo_site_snapshots');
    }
};
