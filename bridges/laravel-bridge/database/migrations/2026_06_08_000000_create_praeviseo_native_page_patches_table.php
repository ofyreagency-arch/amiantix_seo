<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('praeviseo_native_page_patches', function (Blueprint $table): void {
            $table->id();
            $table->string('praeviseo_site_id', 64);
            $table->unsignedBigInteger('external_page_id');
            $table->string('target_path', 255);
            $table->string('title');
            $table->string('h1')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('content_html');
            $table->json('faq_json')->nullable();
            $table->json('schema_json')->nullable();
            $table->json('internal_links_json')->nullable();
            $table->string('canonical_url')->nullable();
            $table->string('live_url')->nullable();
            $table->string('publication_state', 32)->default('published');
            $table->timestamp('last_published_at')->nullable();
            $table->timestamps();

            $table->unique(['praeviseo_site_id', 'target_path']);
            $table->index(['praeviseo_site_id', 'external_page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('praeviseo_native_page_patches');
    }
};
