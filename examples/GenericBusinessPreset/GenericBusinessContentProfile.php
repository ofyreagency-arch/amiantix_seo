<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\GenericBusinessPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;

final class GenericBusinessContentProfile implements NicheContentProvider
{
    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        $topic = Str::headline((string) ($blueprint['topic'] ?? $keyword));
        $links = $context['internal_links'] ?? [];

        $content = implode('', [
            '<section><h2>Operational context</h2><p>'.$topic.' matters when teams need a process they can actually run, review and improve. The page should describe the real workflow, the timing, the people involved and the tradeoffs that create friction in the field.</p></section>',
            '<section><h2>Common friction points</h2><p>Most teams do not struggle with theory. They struggle with unclear ownership, inconsistent execution, missing checks, rushed handoffs and decisions that are never written down clearly enough for the next person.</p></section>',
            '<section><h2>What teams should observe</h2><p>Describe the concrete steps, checkpoints and business signals that show whether the process is working. Name what should be checked, by whom and at what moment in the workflow.</p></section>',
            '<section><h2>Decision criteria</h2><p>Show how a manager should choose between immediate fixes, process changes, tooling changes or training. The goal is to turn vague advice into decisions someone can defend and repeat.</p></section>',
            '<section><h2>Implementation checklist</h2><ul><li>Define the owner for each step.</li><li>Set the review cadence.</li><li>Document the practical checks.</li><li>Record evidence or outcomes after changes.</li><li>Update the page when the workflow changes.</li></ul></section>',
            '<section><h2>Mistakes to avoid</h2><p>Avoid abstract recommendations, missing review cadence, unnamed responsibilities and examples that never connect to the real operating environment.</p></section>',
            $this->internalLinksSection(is_array($links) ? $links : []),
            '<section><h2>Next step</h2><p>Use this page as a working reference: align the team on the process, check how work really happens and adjust the documented steps until the guidance matches the field.</p></section>',
        ]);

        return [
            'title' => $topic.' guide: workflow, checks and implementation',
            'meta_description' => 'Practical '.$topic.' guidance with workflow detail, review checks, ownership and implementation steps.',
            'h1' => $topic.' guide for operational teams',
            'content' => $this->ensureContentDepth($content, $blueprint, $context + ['keyword' => $keyword, 'cluster' => $cluster]),
            'faq' => array_map(static fn (array $item): array => [
                'question' => (string) ($item['question'] ?? ''),
                'answer' => (string) ($item['answer'] ?? ''),
            ], $blueprint['faq'] ?? []),
        ];
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        return '<section><h2>Where this breaks in real life</h2><p>Look for handoff points, rushed moments, overloaded team members and unclear verification steps. Those are usually the places where the written process stops matching the real one.</p></section>';
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        $extraBlocks = [
            '<section><h2>How to review the process</h2><p>Review the page against real operations, not assumptions. Check whether responsibilities are clear, whether teams still follow the sequence and whether the current documentation matches the actual tools and timing.</p></section>',
            '<section><h2>Signs that the page is helping</h2><p>A useful page makes onboarding faster, reduces repeated mistakes and helps managers explain why a process exists, not just what the steps are.</p></section>',
            '<section><h2>What to update after a change</h2><p>Any change in tooling, staffing, workflow or service promise should trigger a fast review. If the page no longer matches the field, it loses credibility immediately.</p></section>',
        ];

        foreach ($extraBlocks as $block) {
            if ($this->wordCount($content) >= 1300) {
                break;
            }

            if (! str_contains($content, $block)) {
                $content .= $block;
            }
        }

        $cycle = 1;
        while ($this->wordCount($content) < 1300 && $cycle <= 4) {
            $content .= '<section><h2>Field example '.$cycle.'</h2><p>Example '.$cycle.' should describe a real operating moment, the decision that has to be made, the owner, the verification step and the follow-up evidence. This is where a generic article becomes a working operational reference.</p></section>';
            $cycle++;
        }

        return $content;
    }

    /**
     * @param  array<int,array{label:string,url:string,reason:string}>  $links
     */
    private function internalLinksSection(array $links): string
    {
        if ($links === []) {
            return '';
        }

        $items = collect($links)
            ->take(5)
            ->map(static fn (array $link): string => '<li><a href="'.$link['url'].'">'.$link['label'].'</a></li>')
            ->implode('');

        return '<section><h2>Related resources</h2><ul>'.$items.'</ul></section>';
    }

    private function wordCount(string $content): int
    {
        return str_word_count(Str::ascii(strip_tags($content)));
    }
}
