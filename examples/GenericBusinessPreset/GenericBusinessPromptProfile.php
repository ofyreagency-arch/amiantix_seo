<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\GenericBusinessPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

final class GenericBusinessPromptProfile implements PromptProfileProvider
{
    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return "Write a business article for a professional SaaS knowledge base.\n".
            'Primary keyword: '.$keyword."\n".
            'Cluster: '.$cluster."\n".
            'Topic: '.$blueprint['topic']."\n".
            'Editorial sections: '.json_encode($editorialSections, JSON_UNESCAPED_SLASHES)."\n".
            'Required signals: '.json_encode($expectedSignals, JSON_UNESCAPED_SLASHES)."\n".
            "Constraints:\n".
            "- write for operators, managers and buyers who need practical clarity\n".
            "- avoid meta SEO talk, AI talk and generic filler\n".
            "- use concrete workflow situations, practical checks, responsibilities and tradeoffs\n".
            "- include a clear checklist, real friction points, FAQ and a grounded conclusion\n".
            "- minimum 1200 words\n".
            'Return JSON only with: title, meta_description, h1, content, faq, schema.';
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return "Improve this business article with more operational depth.\n".
            'Keyword: '.($page->keyword ?? '')."\n".
            'Topic: '.$blueprint['topic']."\n".
            'Issues: '.json_encode($audit['issues'] ?? [], JSON_UNESCAPED_SLASHES)."\n".
            'Recommendations: '.json_encode($audit['recommendations'] ?? [], JSON_UNESCAPED_SLASHES)."\n".
            'Expected sections: '.json_encode($editorialSections, JSON_UNESCAPED_SLASHES)."\n".
            'Required signals: '.json_encode($expectedSignals, JSON_UNESCAPED_SLASHES)."\n".
            "Do not mention SEO, Google or content strategy. Return JSON only with title, meta_description, h1, content, faq and schema.\n".
            "Current content:\n".($page->content ?? '');
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return "Rewrite this professional business article.\n".
            'Mode: '.$mode."\n".
            'Keyword: '.($page->keyword ?? '')."\n".
            'Cluster: '.($page->cluster ?? 'generic-business')."\n".
            "Use concrete business language, practical workflow details and clear next actions.\n".
            "Do not mention SEO, AI, premium content or Google.\n".
            "Return JSON with title, meta_description, h1, sections, faq, internal_links and rationale.\n".
            "Current content:\n".($page->content ?? '');
    }

    public function fallbackRewrite(object $page, string $mode): array
    {
        $topic = Str::headline((string) ($page->keyword ?? 'business workflow'));

        return [
            'mode' => $mode,
            'title' => $mode === 'improve-ctr' ? $topic.' guide: decisions, workflow and implementation checks' : null,
            'meta_description' => $mode === 'improve-ctr' ? 'A practical guide to '.$topic.' with workflow detail, team responsibilities and implementation checks.' : null,
            'h1' => null,
            'sections' => [
                'Replace generic statements with observable workflow steps, decision points and owner responsibilities.',
                'Add a section that explains where teams usually lose time, quality or consistency.',
                'Add a checklist that shows what should be reviewed daily, weekly or after a change.',
            ],
            'faq' => [
                [
                    'question' => 'What should this page help a team decide?',
                    'answer' => 'It should make the operating choices, ownership and review cadence easier to understand and apply.',
                ],
            ],
            'internal_links' => $page->internal_links_json ?? [],
            'rationale' => [
                'The rewrite increases practical depth and removes generic filler.',
            ],
        ];
    }
}
