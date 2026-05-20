<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Ofyre\SeoEngine\Console\Commands\SeoDoctorCommand;
use Ofyre\SeoEngine\Console\Commands\SeoDetectCannibalizationCommand;
use Ofyre\SeoEngine\Console\Commands\SeoEmbedContentCommand;
use Ofyre\SeoEngine\Console\Commands\SeoInstallCommand;
use Ofyre\SeoEngine\Console\Commands\SeoMatchQueryPagesCommand;
use Ofyre\SeoEngine\Console\Commands\SeoQueueSignalSuggestionsCommand;
use Ofyre\SeoEngine\Console\Commands\SeoSemanticLinksCommand;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository;
use Ofyre\SeoEngine\Contracts\HistoricalSeoImporter;
use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Contracts\PrioritizedPageProvider;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Ofyre\SeoEngine\Contracts\RewriteAccessDecider;
use Ofyre\SeoEngine\Contracts\SearchConsoleTokenProvider;
use Ofyre\SeoEngine\Contracts\SemanticLinkPolicyProvider;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\SeoAuditPersister;
use Ofyre\SeoEngine\Contracts\SeoCockpitRepository;
use Ofyre\SeoEngine\Contracts\SeoFeedbackLoopDriver;
use Ofyre\SeoEngine\Contracts\SeoGenerationDriver;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Contracts\SeoSuggestionPersister;
use Ofyre\SeoEngine\Contracts\VectorStore;
use Ofyre\SeoEngine\Console\Commands\SeoFeedbackLoopCommand;
use Ofyre\SeoEngine\Console\Commands\SeoGeneratePageCommand;
use Ofyre\SeoEngine\Console\Commands\SeoImportHistoryCommand;
use Ofyre\SeoEngine\Console\Commands\SeoImprovePageCommand;
use Ofyre\SeoEngine\Console\Commands\SeoPageStatusCommand;
use Ofyre\SeoEngine\Console\Commands\SeoRecalculateScoresCommand;
use Ofyre\SeoEngine\Services\Console\SeoSchedulerRegistrar;

final class SeoEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/seo-engine.php', 'seo-engine');

        $this->bindConfiguredContract(EmbeddingProvider::class, 'embedding_provider');
        $this->bindConfiguredContract(EmbeddableContentRepository::class, 'embeddable_content_repository');
        $this->bindConfiguredContract(HistoricalSeoImporter::class, 'historical_importer');
        $this->bindConfiguredContract(ImagePromptProvider::class, 'image_prompt_provider');
        $this->bindConfiguredContract(InternalLinkProvider::class, 'internal_link_provider');
        $this->bindConfiguredContract(NicheBlueprintProvider::class, 'niche_blueprint_provider');
        $this->bindConfiguredContract(NicheContentProvider::class, 'niche_content_provider');
        $this->bindConfiguredContract(PrioritizedPageProvider::class, 'prioritized_page_provider');
        $this->bindConfiguredContract(PromptProfileProvider::class, 'prompt_profile_provider');
        $this->bindConfiguredContract(RewriteAccessDecider::class, 'rewrite_access_decider');
        $this->bindConfiguredContract(SearchConsoleTokenProvider::class, 'search_console_token_provider');
        $this->bindConfiguredContract(SemanticLinkPolicyProvider::class, 'semantic_link_policy_provider');
        $this->bindConfiguredContract(SemanticLinkRepository::class, 'semantic_link_repository');
        $this->bindConfiguredContract(SeoAuditPersister::class, 'seo_audit_persister');
        $this->bindConfiguredContract(SeoCockpitRepository::class, 'seo_cockpit_repository');
        $this->bindConfiguredContract(SeoFeedbackLoopDriver::class, 'seo_feedback_loop_driver');
        $this->bindConfiguredContract(SeoGenerationDriver::class, 'seo_generation_driver');
        $this->bindConfiguredContract(SeoPageRepository::class, 'seo_page_repository');
        $this->bindConfiguredContract(SeoSuggestionPersister::class, 'seo_suggestion_persister');
        $this->bindConfiguredContract(VectorStore::class, 'vector_store');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            SeoDoctorCommand::class,
            SeoDetectCannibalizationCommand::class,
            SeoEmbedContentCommand::class,
            SeoFeedbackLoopCommand::class,
            SeoGeneratePageCommand::class,
            SeoImportHistoryCommand::class,
            SeoInstallCommand::class,
            SeoImprovePageCommand::class,
            SeoMatchQueryPagesCommand::class,
            SeoPageStatusCommand::class,
            SeoQueueSignalSuggestionsCommand::class,
            SeoRecalculateScoresCommand::class,
            SeoSemanticLinksCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../config/seo-engine.php' => config_path('seo-engine.php'),
        ], 'seo-engine-config');

        $this->app->booted(function (): void {
            $this->app->make(SeoSchedulerRegistrar::class)
                ->register($this->app->make(Schedule::class));
        });
    }

    private function bindConfiguredContract(string $abstract, string $configKey): void
    {
        if ($this->app->bound($abstract)) {
            return;
        }

        $implementation = config('seo-engine.contracts.'.$configKey);

        if (! is_string($implementation) || $implementation === '' || ! class_exists($implementation)) {
            return;
        }

        $this->app->bind($abstract, $implementation);
    }
}
