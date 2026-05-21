<?php

declare(strict_types=1);

namespace App\Presets\Signals;

use Ofyre\SeoEngine\Contracts\ContentSignalProvider;

class GenericContentSignalProvider implements ContentSignalProvider
{
    public function requiredContentMarkers(): array
    {
        return (array) config('seo-engine.quality.content_markers', []);
    }

    public function recommendationFor(string $issueKey): ?string
    {
        $custom = (array) config('seo-engine.quality.content_recommendations', []);

        if (isset($custom[$issueKey])) {
            return $custom[$issueKey];
        }

        return match ($issueKey) {
            'spam_risk_medium' => 'Remove generic marketing phrases and add observable field-level details.',
            'spam_risk_high'   => 'Rewrite the page with a much higher level of professional specificity.',
            default            => null,
        };
    }

    public function genericPhraseWarnings(): array
    {
        return (array) config('seo-engine.quality.generic_phrase_warnings', [
            ['phrase' => 'innovative solution',    'warning' => 'Too generic — replace with a concrete operational benefit.'],
            ['phrase' => 'quality service',        'warning' => 'Replace with measurable outcomes or process guarantees.'],
            ['phrase' => 'personalised support',   'warning' => 'Specify the intervention framework, deliverables and controls instead.'],
        ]);
    }
}
