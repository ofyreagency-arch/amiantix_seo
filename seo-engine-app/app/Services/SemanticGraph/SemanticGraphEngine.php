<?php

declare(strict_types=1);

namespace App\Services\SemanticGraph;

use App\Services\SemanticGraph\Analyzers\CannibalizationAnalyzer;
use App\Services\SemanticGraph\Analyzers\ContentGapAnalyzer;
use App\Services\SemanticGraph\Analyzers\InternalLinkingAnalyzer;
use App\Services\SemanticGraph\Analyzers\QueryOpportunityAnalyzer;
use App\Services\SemanticGraph\Analyzers\SemanticNeighborAnalyzer;

class SemanticGraphEngine
{
    public function __construct(
        private readonly SemanticNeighborAnalyzer $neighbors,
        private readonly CannibalizationAnalyzer $cannibalization,
        private readonly InternalLinkingAnalyzer $internalLinks,
        private readonly QueryOpportunityAnalyzer $queries,
        private readonly ContentGapAnalyzer $gaps,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function build(string $siteId, bool $forceEmbeddings = false): array
    {
        $neighbors = $this->neighbors->analyze($siteId, $forceEmbeddings);
        $cannibalization = $this->cannibalization->analyze($siteId);
        $internalLinks = $this->internalLinks->analyze($siteId);
        $queries = $this->queries->analyze($siteId, $forceEmbeddings);
        $gaps = $this->gaps->analyze($siteId);

        return [
            'semantic_neighbors' => $neighbors,
            'cannibalization_risks' => $cannibalization,
            'internal_link_suggestions' => $internalLinks,
            'query_opportunities' => $queries,
            'content_gaps' => $gaps,
        ];
    }
}
