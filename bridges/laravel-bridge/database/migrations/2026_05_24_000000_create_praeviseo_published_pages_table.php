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
            $table->string('praeviseo_site_id', 190)->index();
            $table->unsignedBigInteger('external_page_id')->index();
            $table->string('slug', 190)->unique();
            $table->string('title');
            $table->string('h1')->nullable();
            $table->string('meta_description')->nullable();
            $table->longText('content_html');
            $table->json('faq_json')->nullable();
            $table->json('schema_json')->nullable();
            $table->json('internal_links_json')->nullable();
            $table->string('canonical_url', 500)->nullable();
            $table->string('live_url', 500)->nullable();
            $table->string('cluster', 120)->nullable();
            $table->boolean('is_noindex')->default(false);
            $table->string('image_path', 500)->nullable();
            $table->string('image_alt')->nullable();
            $table->string('publication_state', 40)->default('published');
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();

            $table->unique(['praeviseo_site_id', 'external_page_id'], 'praeviseo_site_page_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('praeviseo_published_pages');
    }
};
