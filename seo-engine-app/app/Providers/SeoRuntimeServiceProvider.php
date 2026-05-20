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
use App\Services\DatabasePrioritizedPageProvider;
use App\Services\GenericContentSignalProvider;
use App\Services\RuntimePageStatusLabeler;
use App\Services\RuntimeSeoMonitoringService;
use App\Services\RuntimeSignalSuggestionFormatter;
use App\Services\SeoOverrideService;
use App\SeoPresets\Generic\GenericBlueprintProvider;
use App\SeoPresets\Generic\GenericContentProfile;
use App\SeoPresets\Generic\GenericImagePromptProvider;
use App\SeoPresets\Generic\GenericInternalLinkProvider;
use App\SeoPresets\Generic\GenericPromptProfile;
use Illuminate\Support\ServiceProvider;
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
        $this->app->singleton(SeoPageRepository::class, MysqlSeoPageRepository::class);
        $this->app->singleton(SeoGenerationDriver::class, OpenAiSeoGenerationDriver::class);
        $this->app->singleton(SeoFeedbackLoopDriver::class, DatabaseSeoFeedbackLoopDriver::class);
        $this->app->singleton(HistoricalSeoImporter::class, SearchConsoleHistoricalImporter::class);
        $this->app->singleton(EmbeddingProvider::class, OpenAiEmbeddingProvider::class);
        $this->app->singleton(EmbeddableContentRepositoryContract::class, EmbeddableContentRepository::class);
        $this->app->singleton(VectorStore::class, MysqlVectorStore::class);
        $this->app->singleton(SemanticLinkRepository::class, MysqlSemanticLinkRepository::class);
        $this->app->singleton(SeoAuditPersister::class, DatabaseSeoAuditPersister::class);
        $this->app->singleton(SeoSuggestionPersister::class, DatabaseSeoSuggestionPersister::class);
        $this->app->singleton(SeoCockpitRepository::class, DatabaseSeoCockpitRepository::class);
        $this->app->singleton(SearchConsoleTokenProvider::class, GoogleServiceAccountTokenService::class);
        $this->app->singleton(RewriteAccessDecider::class, SeoOverrideService::class);
        $this->app->singleton(ContentSignalProvider::class, GenericContentSignalProvider::class);
        $this->app->singleton(PageStatusLabeler::class, RuntimePageStatusLabeler::class);
        $this->app->singleton(PrioritizedPageProvider::class, DatabasePrioritizedPageProvider::class);
        $this->app->singleton(SignalSuggestionFormatter::class, RuntimeSignalSuggestionFormatter::class);

        $this->app->singleton(NicheBlueprintProvider::class, GenericBlueprintProvider::class);
        $this->app->singleton(PromptProfileProvider::class, GenericPromptProfile::class);
        $this->app->singleton(NicheContentProvider::class, GenericContentProfile::class);
        $this->app->singleton(InternalLinkProvider::class, GenericInternalLinkProvider::class);
        $this->app->singleton(ImagePromptProvider::class, GenericImagePromptProvider::class);

        $this->app->singleton(RuntimeSeoMonitoringService::class);
    }
}
