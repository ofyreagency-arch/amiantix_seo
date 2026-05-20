<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_sites', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id', 64)->unique();
            $table->string('name');
            $table->string('url');
            $table->string('niche', 100)->default('general');
            $table->string('locale', 20)->default('en');
            $table->string('api_token_hash', 64)->unique();
            $table->string('webhook_url')->nullable();
            $table->string('gsc_site_url')->nullable();
            $table->string('gsc_credentials_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_sites');
    }
};
