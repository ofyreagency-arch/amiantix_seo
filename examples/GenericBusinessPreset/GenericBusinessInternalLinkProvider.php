<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Examples\GenericBusinessPreset;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;

final class GenericBusinessInternalLinkProvider implements InternalLinkProvider
{
    public function linksFor(object $page): array
    {
        return [
            [
                'label' => 'Implementation checklist',
                'url' => '/implementation-checklist',
                'reason' => 'operations',
            ],
            [
                'label' => 'Process review template',
                'url' => '/process-review-template',
                'reason' => 'review',
            ],
            [
                'label' => 'Team onboarding workflow',
                'url' => '/team-onboarding-workflow',
                'reason' => 'enablement',
            ],
        ];
    }

    public function clusterForKeyword(string $keyword): string
    {
        $normalized = Str::lower(Str::ascii($keyword));

        return match (true) {
            str_contains($normalized, 'compliance') => 'compliance',
            str_contains($normalized, 'workflow'), str_contains($normalized, 'process') => 'operations',
            str_contains($normalized, 'onboarding'), str_contains($normalized, 'training') => 'enablement',
            default => 'generic-business',
        };
    }
}
