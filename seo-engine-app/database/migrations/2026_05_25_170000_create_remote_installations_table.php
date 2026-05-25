<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_installations', function (Blueprint $table): void {
            $table->id();
            $table->string('site_id', 64)->index();
            $table->string('status', 40)->default('pending')->index();
            $table->string('current_step', 80)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('hosting_provider', 80)->nullable();
            $table->string('connection_type', 40);
            $table->json('encrypted_credentials')->nullable();
            $table->json('connection_metadata')->nullable();
            $table->string('detected_framework', 40)->nullable();
            $table->string('detected_php_version', 40)->nullable();
            $table->string('detected_composer', 255)->nullable();
            $table->json('logs_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table
                ->foreign('site_id')
                ->references('site_id')
                ->on('seo_sites')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('remote_installations');
    }
};
