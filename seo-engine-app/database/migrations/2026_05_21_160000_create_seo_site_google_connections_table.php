<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seo_site_google_connections')) {
            Schema::create('seo_site_google_connections', function (Blueprint $table): void {
                $table->id();
                $table->string('site_id', 64)->unique();
                $table->string('connection_mode', 40)->default('service_account');
                $table->string('property_url', 500)->nullable();
                $table->string('property_label')->nullable();
                $table->string('google_account_email')->nullable();
                $table->text('refresh_token_encrypted')->nullable();
                $table->timestamp('access_token_expires_at')->nullable();
                $table->string('credentials_path', 500)->nullable();
                $table->string('connection_status', 40)->default('not_connected');
                $table->timestamp('last_validated_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->text('last_error')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamps();

                $table->foreign('site_id')
                    ->references('site_id')
                    ->on('seo_sites')
                    ->cascadeOnDelete();

                $table->index(['connection_status', 'connection_mode'], 'seo_site_google_conn_status_mode_idx');
            });
        }

        $legacySites = DB::table('seo_sites')
            ->select(['site_id', 'gsc_site_url', 'gsc_credentials_path', 'created_at', 'updated_at'])
            ->where(function ($query): void {
                $query->whereNotNull('gsc_site_url')
                    ->orWhereNotNull('gsc_credentials_path');
            })
            ->get();

        foreach ($legacySites as $site) {
            DB::table('seo_site_google_connections')->updateOrInsert(
                ['site_id' => $site->site_id],
                [
                    'connection_mode' => 'service_account',
                    'property_url' => $site->gsc_site_url,
                    'credentials_path' => $site->gsc_credentials_path,
                    'connection_status' => ($site->gsc_site_url || $site->gsc_credentials_path) ? 'configured' : 'not_connected',
                    'created_at' => $site->created_at ?? now(),
                    'updated_at' => $site->updated_at ?? now(),
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_site_google_connections');
    }
};
