<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('praeviseo_published_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('praeviseo_site_id');
            $table->unsignedBigInteger('external_page_id');
            $table->string('slug');
            $table->string('title');
            $table->string('h1')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('content_html');
            $table->json('faq_json')->nullable();
            $table->json('schema_json')->nullable();
            $table->json('internal_links_json')->nullable();
            $table->text('canonical_url')->nullable();
            $table->text('live_url')->nullable();
            $table->string('cluster')->nullable();
            $table->boolean('is_noindex')->default(false);
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('publication_state')->default('published');
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();

            $table->unique(['praeviseo_site_id', 'external_page_id'], 'praeviseo_page_unique');
            $table->unique(['praeviseo_site_id', 'slug'], 'praeviseo_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('praeviseo_published_pages');
    }
};
