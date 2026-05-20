<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SeoPage;
use App\Models\SeoStrategyItem;
use App\Models\SeoSite;
use Illuminate\Support\Facades\Http;

class SiteStrategyService
{
    public function generate(SeoSite $site): array
    {
        $pages    = SeoPage::query()->where('site_id', $site->site_id)->get(['keyword', 'cluster', 'status', 'seo_score']);
        $clusters = $pages->groupBy('cluster')->map->count()->sortDesc()->take(15)->toArray();
        $keywords = $pages->pluck('keyword')->take(20)->toArray();

        $prompt = $this->buildPrompt($site, $pages->count(), $clusters, $keywords);

        $response = Http::withToken((string) config('services.openai.api_key'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'           => config('services.openai.model', 'gpt-4o-mini'),
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => 'You are an expert SEO strategist. Respond only with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        $data  = json_decode($response->json('choices.0.message.content'), true) ?? [];
        $items = $data['strategy'] ?? [];

        SeoStrategyItem::query()->where('site_id', $site->site_id)->delete();

        $saved = [];
        foreach ($items as $i => $item) {
            $saved[] = SeoStrategyItem::query()->create([
                'site_id'          => $site->site_id,
                'priority'         => $item['priority'] ?? ($i + 1),
                'type'             => $item['type'] ?? 'page',
                'title'            => $item['title'] ?? '',
                'description'      => $item['description'] ?? '',
                'keywords_json'    => $item['keywords'] ?? [],
                'estimated_impact' => $item['impact'] ?? 'medium',
                'status'           => 'pending',
                'generated_at'     => now(),
            ]);
        }

        return $saved;
    }

    public function items(string $siteId): \Illuminate\Database\Eloquent\Collection
    {
        return SeoStrategyItem::query()
            ->where('site_id', $siteId)
            ->orderBy('priority')
            ->get();
    }

    public function markDone(int $id): void
    {
        SeoStrategyItem::query()->findOrFail($id)->update(['status' => 'done']);
    }

    private function buildPrompt(SeoSite $site, int $count, array $clusters, array $keywords): string
    {
        $clusterList = implode(', ', array_keys($clusters));
        $keywordList = implode(', ', $keywords);

        return <<<PROMPT
Site: {$site->name}
URL: {$site->url}
Niche: {$site->niche}
Locale: {$site->locale}
Current pages: {$count}
Clusters covered: {$clusterList}
Sample keywords: {$keywordList}

Generate a prioritized SEO strategy with exactly 12 specific action items.
Each item must be actionable, specific to this niche, and realistic.

Respond with this exact JSON structure:
{
  "strategy": [
    {
      "priority": 1,
      "type": "page|cluster|technical|link|content",
      "title": "Short action title",
      "description": "Specific explanation of what to do and why (2-3 sentences)",
      "keywords": ["keyword1", "keyword2"],
      "impact": "high|medium|low"
    }
  ]
}
PROMPT;
    }
}
