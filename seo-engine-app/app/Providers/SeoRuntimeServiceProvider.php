<?php

declare(strict_types=1);

namespace App\Providers;

use App\SeoBridge\Drivers\OpenAiSeoGenerationDriver;
use App\SeoBridge\Embeddings\OpenAiEmbeddingProvider;
use App\SeoBridge\Feedback\DatabaseSeoFeedbackLoopDriver;
use App\SeoBridge\Persisters\DatabaseSeoAuditPersister;
use App\SeoBridge\Persisters\DatabaseSeoSuggestionPersister;
use App\SeoBridge\Repositories\DatabaseSeoCockpitRepository;
use App\SeoBridge\Repositories\EmbeddableContentRepository;
use App\SeoBridge\Repositories\MysqlSemanticLinkRepository;
use App\SeoBridge\Repositories\MysqlSeoPageRepository;
use App\SeoBridge\SearchConsole\SearchConsoleHistoricalImporter;
use App\SeoBridge\VectorStore\MysqlVectorStore;
use App\ActionLayer\SeoOverrideService;
use App\Runtime\SeoEngineContext;
use App\Runtime\DatabasePrioritizedPageProvider;
use App\Runtime\RuntimePageStatusLabeler;
use App\Runtime\RuntimeSeoMonitoringService;
use App\Runtime\RuntimeSignalSuggestionFormatter;
use App\Services\Preset\PresetBlueprintProvider;
use App\Services\Preset\PresetContentProfile;
use App\Services\Preset\PresetContentSignalProvider;
use App\Services\Preset\PresetImagePromptProvider;
use App\Services\Preset\PresetInternalLinkProvider;
use App\Services\Preset\PresetManager;
use App\Services\Preset\PresetPromptProfile;
use Illuminate\Support\ServiceProvider;
use Ofyre\SeoEngine\Contracts\CannibalizationActionDecider;
use Ofyre\SeoEngine\Contracts\ContentSignalProvider;
use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository as EmbeddableContentRepositoryContract;
use Ofyre\SeoEngine\Contracts\EmbeddingProvider;
use Ofyre\SeoEngine\Contracts\HistoricalSeoImporter;
use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Contracts\PageStatusLabeler;
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
use Ofyre\SeoEngine\Contracts\SignalSuggestionFormatter;
use Ofyre\SeoEngine\Contracts\VectorStore;
use Ofyre\SeoEngine\Services\SearchConsole\GoogleServiceAccountTokenService;

class SeoRuntimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SeoEngineContext::class);
        $this->app->singleton(PresetManager::class);

        $this->app->singleton(SeoPageRepository::class, MysqlSeoPageRepository::class);
        $this->app->singleton(SeoGenerationDriver::class, OpenAiSeoGenerationDriver::class);
        $this->app->singleton(SeoFeedbackLoopDriver::class, DatabaseSeoFeedbackLoopDriver::class);
        $this->app->singleton(HistoricalSeoImporter::class, SearchConsoleHistoricalImporter::class);
        $this->app->singleton(EmbeddingProvider::class, OpenAiEmbeddingProvider::class);
        $this->app->singleton(EmbeddableContentRepositoryContract::class, EmbeddableContentRepository::class);
        $this->app->singleton(VectorStore::class, MysqlVectorStore::class);
        $this->app->singleton(CannibalizationActionDecider::class, \Ofyre\SeoEngine\Services\Embeddings\DefaultCannibalizationActionDecider::class);
        $this->app->singleton(SemanticLinkPolicyProvider::class, \Ofyre\SeoEngine\Services\Embeddings\ConfigDrivenSemanticLinkPolicy::class);
        $this->app->singleton(SemanticLinkRepository::class, MysqlSemanticLinkRepository::class);
        $this->app->singleton(SeoAuditPersister::class, DatabaseSeoAuditPersister::class);
        $this->app->singleton(SeoSuggestionPersister::class, DatabaseSeoSuggestionPersister::class);
        $this->app->singleton(SeoCockpitRepository::class, DatabaseSeoCockpitRepository::class);
        $this->app->singleton(SearchConsoleTokenProvider::class, GoogleServiceAccountTokenService::class);
        $this->app->singleton(RewriteAccessDecider::class, SeoOverrideService::class);
        $this->app->singleton(ContentSignalProvider::class, PresetContentSignalProvider::class);
        $this->app->singleton(PageStatusLabeler::class, RuntimePageStatusLabeler::class);
        $this->app->singleton(PrioritizedPageProvider::class, DatabasePrioritizedPageProvider::class);
        $this->app->singleton(SignalSuggestionFormatter::class, RuntimeSignalSuggestionFormatter::class);

        $this->app->singleton(NicheBlueprintProvider::class, PresetBlueprintProvider::class);
        $this->app->singleton(PromptProfileProvider::class, PresetPromptProfile::class);
        $this->app->singleton(NicheContentProvider::class, PresetContentProfile::class);
        $this->app->singleton(InternalLinkProvider::class, PresetInternalLinkProvider::class);
        $this->app->singleton(ImagePromptProvider::class, PresetImagePromptProvider::class);

        $this->app->singleton(RuntimeSeoMonitoringService::class);
    }
}
