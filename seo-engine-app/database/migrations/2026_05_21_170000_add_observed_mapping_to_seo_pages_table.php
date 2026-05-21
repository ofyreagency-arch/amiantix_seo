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
        Schema::table('seo_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('seo_pages', 'observed_site_page_id')) {
                $table->foreignId('observed_site_page_id')
                    ->nullable()
                    ->after('site_id')
                    ->constrained('seo_site_pages')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('seo_pages', 'observed_page_match_rule')) {
                $table->string('observed_page_match_rule', 40)
                    ->nullable()
                    ->after('observed_site_page_id');
            }

            if (! Schema::hasColumn('seo_pages', 'observed_page_linked_at')) {
                $table->timestamp('observed_page_linked_at')
                    ->nullable()
                    ->after('observed_page_match_rule');
            }
        });

        if (! Schema::hasColumn('seo_pages', 'observed_site_page_id')) {
            return;
        }

        $pages = DB::table('seo_pages')
            ->select('id', 'site_id', 'slug', 'canonical_url')
            ->whereNull('observed_site_page_id')
            ->orderBy('id')
            ->get();

        foreach ($pages as $page) {
            $canonicalUrl = is_string($page->canonical_url) ? trim($page->canonical_url) : '';
            $path = '/'.ltrim((string) $page->slug, '/');
            $rule = null;

            $observed = null;

            if ($canonicalUrl !== '') {
                $observed = DB::table('seo_site_pages')
                    ->select('id')
                    ->where('site_id', $page->site_id)
                    ->where('normalized_url', $canonicalUrl)
                    ->first();
                $rule = $observed ? 'canonical_url_exact' : null;
            }

            if (! $observed) {
                $observed = DB::table('seo_site_pages')
                    ->select('id')
                    ->where('site_id', $page->site_id)
                    ->where('path', $path)
                    ->orderByDesc('last_seen_at')
                    ->first();
                $rule = $observed ? 'path_exact' : $rule;
            }

            if (! $observed) {
                continue;
            }

            DB::table('seo_pages')
                ->where('id', $page->id)
                ->update([
                    'observed_site_page_id' => $observed->id,
                    'observed_page_match_rule' => $rule,
                    'observed_page_linked_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('seo_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('seo_pages', 'observed_site_page_id')) {
                $table->dropConstrainedForeignId('observed_site_page_id');
            }

            if (Schema::hasColumn('seo_pages', 'observed_page_match_rule')) {
                $table->dropColumn('observed_page_match_rule');
            }

            if (Schema::hasColumn('seo_pages', 'observed_page_linked_at')) {
                $table->dropColumn('observed_page_linked_at');
            }
        });
    }
};
