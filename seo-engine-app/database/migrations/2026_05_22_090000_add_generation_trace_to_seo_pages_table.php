<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $afterColumn = $this->existingAfterColumn([
            'last_observed_at',
            'live_url',
            'published_live_at',
            'published_live',
            'published_at',
            'is_indexed',
        ]);

        Schema::table('seo_pages', function (Blueprint $table) use ($afterColumn): void {
            if (! Schema::hasColumn('seo_pages', 'generation_source')) {
                $column = $table->string('generation_source', 32)->nullable();

                if ($afterColumn !== null) {
                    $column->after($afterColumn);
                }
            }

            if (! Schema::hasColumn('seo_pages', 'generation_error')) {
                $column = $table->text('generation_error')->nullable();

                if (Schema::hasColumn('seo_pages', 'generation_source')) {
                    $column->after('generation_source');
                } elseif ($afterColumn !== null) {
                    $column->after($afterColumn);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('seo_pages', 'generation_error')) {
                $table->dropColumn('generation_error');
            }

            if (Schema::hasColumn('seo_pages', 'generation_source')) {
                $table->dropColumn('generation_source');
            }
        });
    }

    /**
     * @param  array<int, string>  $candidates
     */
    private function existingAfterColumn(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (Schema::hasColumn('seo_pages', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
};
