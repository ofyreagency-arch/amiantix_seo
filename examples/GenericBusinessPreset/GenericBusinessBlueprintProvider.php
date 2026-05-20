<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\GenericBusinessPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;

final class GenericBusinessBlueprintProvider implements NicheBlueprintProvider
{
    public function resolve(string $keyword, ?string $cluster = null): array
    {
        $topic = $this->topicFromKeyword($keyword);

        return [
            'topic' => $topic,
            'cluster' => $cluster ?: 'generic-business',
            'hero_angle' => 'practical guide for operators and decision makers',
            'risk_terms' => [
                $topic,
                'operations',
                'compliance',
                'customer experience',
                'quality control',
                'team coordination',
                'daily workflow',
            ],
            'editorial_sections' => [
                'Operational context',
                'Common friction points',
                'What teams should observe',
                'Decision criteria',
                'Implementation checklist',
                'Mistakes to avoid',
                'FAQ',
            ],
            'faq' => [
                [
                    'question' => 'What should a good '.$topic.' page explain first?',
                    'answer' => 'It should start with the real operating context, the decisions involved and the constraints that matter in the field.',
                ],
                [
                    'question' => 'How detailed should the examples be?',
                    'answer' => 'Use concrete situations, named responsibilities, practical checks and a clear sequence of actions.',
                ],
                [
                    'question' => 'What makes a page feel generic?',
                    'answer' => 'Vague advice, repeated filler phrases and explanations that never connect to a real workflow or business choice.',
                ],
                [
                    'question' => 'How should the page help a manager?',
                    'answer' => 'It should help them understand tradeoffs, assign ownership and verify that the right actions are happening consistently.',
                ],
                [
                    'question' => 'What should the conclusion do?',
                    'answer' => 'Close with a practical next step, not a marketing claim or abstract summary.',
                ],
            ],
        ];
    }

    public function expectedEditorialSections(array $profile): array
    {
        return $profile['editorial_sections'] ?? [];
    }

    public function expectedSignals(array $profile): array
    {
        return $profile['risk_terms'] ?? [];
    }

    private function topicFromKeyword(string $keyword): string
    {
        $normalized = Str::of(Str::ascii(Str::lower($keyword)))
            ->replace('-', ' ')
            ->squish()
            ->value();

        return $normalized !== '' ? $normalized : 'business operations';
    }
}
