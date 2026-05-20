<?php

declare(strict_types=1);

namespace App\SeoBridge\Persisters;

use App\Models\SeoAudit;
use App\Models\SeoPage;
use Ofyre\SeoEngine\Contracts\SeoAuditPersister;

class DatabaseSeoAuditPersister implements SeoAuditPersister
{
    public function persist(object $page, array $audit, array $searchConsoleData = []): void
    {
        $pageId = (int) ($page->id ?? 0);

        if ($pageId <= 0 && $page instanceof SeoPage) {
            $pageId = $page->getKey();
        }

        if ($pageId <= 0) {
            return;
        }

        SeoAudit::query()->create([
            'seo_page_id' => $pageId,
            'score' => (int) ($audit['score'] ?? 0),
            'issues_json' => $audit['issues'] ?? [],
            'recommendations_json' => $audit['recommendations'] ?? [],
            'search_console_json' => $searchConsoleData,
        ]);
    }
}
