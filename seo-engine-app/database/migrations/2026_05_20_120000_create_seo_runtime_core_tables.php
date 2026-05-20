<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id')->default('default');
            $table->string('keyword');
            $table->string('slug')->unique();
            $table->string('cluster')->nullable();
            $table->string('status')->default('draft');
            $table->string('title')->nullable();
            $table->string('h1')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('content')->nullable();
            $table->json('faq_json')->nullable();
            $table->json('schema_json')->nullable();
            $table->json('internal_links_json')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('forced_noindex')->default(false);
            $table->boolean('is_indexed')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->text('image_prompt')->nullable();
            $table->string('image_status')->default('missing');
            $table->json('image_quality_json')->nullable();
            $table->unsignedTinyInteger('topical_score')->default(0);
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->unsignedTinyInteger('seo_score')->default(0);
            $table->unsignedTinyInteger('indexability_score')->default(0);
            $table->unsignedTinyInteger('image_quality_score')->default(0);
            $table->unsignedTinyInteger('duplicate_risk_score')->default(0);
            $table->string('spam_risk')->default('low');
            $table->json('review_issues_json')->nullable();
            $table->timestamp('last_audit_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['cluster', 'status']);
        });

        Schema::create('seo_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_page_id')->constrained('seo_pages')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->json('issues_json')->nullable();
            $table->json('recommendations_json')->nullable();
            $table->json('search_console_json')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_page_id')->constrained('seo_pages')->cascadeOnDelete();
            $table->string('source');
            $table->json('signals_json')->nullable();
            $table->json('suggestions_json')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['seo_page_id', 'source', 'status']);
        });

        Schema::create('seo_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('seo_page_id')->constrained('seo_pages')->cascadeOnDelete();
            $table->boolean('rewrite_allowed')->default(true);
            $table->boolean('forced_noindex')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('seo_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_overrides');
        Schema::dropIfExists('seo_suggestions');
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('seo_pages');
    }
};
