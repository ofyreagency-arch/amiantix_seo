<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

class SeoPageStatusPresenter
{
    /**
     * @param  array<string,mixed>  $status
     * @return array{summary:array<int,string>,blocking:array<int,string>,warnings:array<int,string>,recommendations:array<int,string>}
     */
    public function present(array $status): array
    {
        $scores = $status['scores'] ?? [];

        return [
            'summary' => [
                'Slug : '.$status['slug'],
                'Status : '.$status['status'],
                'SEO score : '.($scores['seo_score'] ?? 'n/a'),
                'Indexability : '.($scores['indexability_score'] ?? 'n/a'),
                'Spam risk : '.($scores['spam_risk'] ?? 'n/a'),
                'Image approved : '.(($scores['image_approved'] ?? false) ? 'yes' : 'no'),
                'FAQ count : '.($scores['faq_count'] ?? 0),
                'Duplicate risk : '.($scores['duplicate_risk_score'] ?? 'n/a'),
            ],
            'blocking' => $status['blocking_reasons'] === []
                ? ['Blocking reasons : none']
                : array_merge(
                    ['Blocking reasons :'],
                    array_map(static fn (string $reason): string => '* '.$reason, $status['blocking_reasons'])
                ),
            'warnings' => array_map(static fn (string $warning): string => '* '.$warning, $status['warnings'] ?? []),
            'recommendations' => array_map(static fn (string $recommendation): string => '* '.$recommendation, $status['recommendations'] ?? []),
        ];
    }
}
