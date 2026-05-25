<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_seo_sites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seo_site_id')->constrained('seo_sites')->cascadeOnDelete();
            $table->string('role', 32)->default('owner');
            $table->timestamps();

            $table->unique(['user_id', 'seo_site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_seo_sites');
    }
};
