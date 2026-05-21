<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_site_crawls', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->text('base_url');
            $table->string('status')->default('running');
            $table->unsignedSmallInteger('max_pages')->default(80);
            $table->unsignedSmallInteger('discovered_url_count')->default(0);
            $table->unsignedSmallInteger('crawled_url_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'started_at']);
        });

        Schema::create('seo_site_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->text('normalized_url');
            $table->string('url_hash', 64);
            $table->string('path', 2048)->nullable();
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->string('primary_h1')->nullable();
            $table->string('indexability_state')->default('unknown');
            $table->unsignedSmallInteger('last_status_code')->nullable();
            $table->unsignedBigInteger('last_crawl_id')->nullable();
            $table->unsignedBigInteger('last_snapshot_id')->nullable();
            $table->unsignedInteger('latest_word_count')->default(0);
            $table->unsignedSmallInteger('internal_inlinks')->default(0);
            $table->unsignedSmallInteger('internal_outlinks')->default(0);
            $table->decimal('authority_score', 6, 4)->default(0);
            $table->decimal('orphan_score', 6, 4)->default(0);
            $table->decimal('overlap_score', 6, 4)->default(0);
            $table->decimal('pillar_likelihood', 6, 4)->default(0);
            $table->string('cluster_label')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url_hash']);
            $table->index(['site_id', 'cluster_label']);
            $table->index(['site_id', 'last_crawl_id']);
        });

        Schema::create('seo_site_page_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedBigInteger('site_crawl_id');
            $table->unsignedBigInteger('site_page_id');
            $table->text('url');
            $table->string('title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->json('h1_json')->nullable();
            $table->json('h2_json')->nullable();
            $table->json('h3_json')->nullable();
            $table->longText('content_text')->nullable();
            $table->longText('content_html')->nullable();
            $table->text('robots_meta')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedSmallInteger('internal_links_count')->default(0);
            $table->unsignedSmallInteger('outlinks_count')->default(0);
            $table->unsignedSmallInteger('schema_count')->default(0);
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'site_crawl_id']);
            $table->index(['site_id', 'site_page_id']);
        });

        Schema::create('seo_site_links', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedBigInteger('site_crawl_id')->nullable();
            $table->unsignedBigInteger('source_page_id')->nullable();
            $table->unsignedBigInteger('target_page_id')->nullable();
            $table->text('source_url');
            $table->text('target_url');
            $table->string('anchor_text')->nullable();
            $table->string('relation_type')->default('internal');
            $table->boolean('is_internal')->default(true);
            $table->boolean('is_nofollow')->default(false);
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'site_crawl_id']);
            $table->index(['site_id', 'source_page_id']);
            $table->index(['site_id', 'target_page_id']);
        });

        Schema::create('seo_site_sitemaps', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedBigInteger('site_crawl_id')->nullable();
            $table->text('url');
            $table->string('url_hash', 64);
            $table->string('sitemap_type')->default('sitemap');
            $table->text('parent_url')->nullable();
            $table->timestamp('lastmod_at')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'url_hash']);
            $table->index(['site_id', 'site_crawl_id']);
        });

        Schema::create('seo_site_schemas', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedBigInteger('site_crawl_id')->nullable();
            $table->unsignedBigInteger('site_page_id')->nullable();
            $table->text('page_url');
            $table->string('schema_type')->nullable();
            $table->json('schema_json')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('observed_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'site_page_id']);
            $table->index(['site_id', 'site_crawl_id']);
        });

        Schema::create('seo_site_crawl_issues', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedBigInteger('site_crawl_id')->nullable();
            $table->unsignedBigInteger('site_page_id')->nullable();
            $table->string('issue_type');
            $table->string('severity')->default('info');
            $table->text('url')->nullable();
            $table->text('details')->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'site_crawl_id']);
            $table->index(['site_id', 'severity']);
            $table->index(['site_id', 'issue_type']);
        });

        Schema::create('seo_recommendations', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id');
            $table->unsignedBigInteger('site_page_id')->nullable();
            $table->unsignedBigInteger('site_crawl_id')->nullable();
            $table->string('type');
            $table->unsignedTinyInteger('priority')->default(50);
            $table->string('estimated_impact')->default('medium');
            $table->string('difficulty')->default('medium');
            $table->string('cluster')->nullable();
            $table->string('title');
            $table->text('reasoning');
            $table->text('suggested_action')->nullable();
            $table->string('status')->default('pending');
            $table->json('meta_json')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'priority']);
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_recommendations');
        Schema::dropIfExists('seo_site_crawl_issues');
        Schema::dropIfExists('seo_site_schemas');
        Schema::dropIfExists('seo_site_sitemaps');
        Schema::dropIfExists('seo_site_links');
        Schema::dropIfExists('seo_site_page_snapshots');
        Schema::dropIfExists('seo_site_pages');
        Schema::dropIfExists('seo_site_crawls');
    }
};
