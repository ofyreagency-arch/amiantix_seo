# Amiantix Runtime VPS

Ce guide transforme `ofyre/seo-engine` en microservice SEO autonome pour Amiantix.

Architecture cible :

```text
[Amiantix Symfony]
    -> appels API prives HTTPS
[seo-engine-app Laravel sur VPS]
    -> charge ofyre/seo-engine
    -> expose API privee
    -> pilote DB / Redis / workers / scheduler
    -> branche OpenAI / Search Console / embeddings / autopilot
```

## 1. Structure serveur

Sur le VPS :

```bash
cd /var/www
mkdir -p seo-engine-app ofyre-seo-engine
git clone git@github.com:ofyreagency-arch/ofyre-seo-engine.git ofyre-seo-engine
composer create-project laravel/laravel seo-engine-app
```

## 2. Connecter le package moteur

Dans `seo-engine-app` :

```bash
cd /var/www/seo-engine-app
composer config repositories.ofyre-seo-engine '{"type":"path","url":"../ofyre-seo-engine","options":{"symlink":false}}'
composer require ofyre/seo-engine:@dev
php artisan seo:install
```

## 3. Variables d environnement

Exemple `.env` :

```dotenv
APP_NAME="SEO Engine"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seo.amiantix.fr

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seo_engine
DB_USERNAME=seo_engine
DB_PASSWORD=change-me

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

OPENAI_API_KEY=...
OPENAI_MODEL=gpt-4o-mini
OPENAI_IMAGE_MODEL=gpt-image-1

SEARCH_CONSOLE_ENABLED=true
GOOGLE_APPLICATION_CREDENTIALS=/var/www/seo-engine-app/storage/google/service-account.json
GOOGLE_SEARCH_CONSOLE_SITE_URL=sc-domain:amiantix.com

SEO_ENGINE_API_TOKEN=super-long-private-token
SEO_SITE_NAME=Amiantix
SEO_SITE_URL=https://amiantix.com
SEO_NICHE=amiante
SEO_LOCALE=fr-FR
SEO_EMBEDDINGS_ENABLED=true
SEO_EMBEDDINGS_MODEL=text-embedding-3-small
```

Dans `config/services.php` du runtime Laravel :

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
    'request_timeout' => 180,
    'connect_timeout' => 30,
    'retry_attempts' => 3,
    'retry_delay_ms' => 2000,
],
```

## 4. Runtime bridge attendu

Creer dans l app Laravel hote :

```text
app/SeoBridge/
app/SeoBridge/Repositories/
app/SeoBridge/Drivers/
app/SeoBridge/Persisters/
app/SeoBridge/Embeddings/
app/SeoBridge/SearchConsole/
app/SeoBridge/Feedback/
app/SeoBridge/VectorStore/
app/SeoPresets/Amiantix/
app/Providers/SeoRuntimeServiceProvider.php
```

Implementations minimales a fournir :

- `MysqlSeoPageRepository`
- `OpenAiSeoGenerationDriver`
- `DatabaseSeoFeedbackLoopDriver`
- `SearchConsoleHistoricalImporter`
- `OpenAiEmbeddingProvider` ou reuse du provider package
- `MysqlVectorStore`
- `MysqlSemanticLinkRepository`
- `EmbeddableContentRepository`
- `SeoAuditPersister`
- `SeoSuggestionPersister`
- `SeoOverrideService`

## 5. Bindings conseilles

Dans `app/Providers/SeoRuntimeServiceProvider.php` :

```php
use App\SeoBridge\Drivers\OpenAiSeoGenerationDriver;
use App\SeoBridge\Embeddings\MysqlVectorStore;
use App\SeoBridge\Feedback\DatabaseSeoFeedbackLoopDriver;
use App\SeoBridge\Persisters\DatabaseSeoAuditPersister;
use App\SeoBridge\Persisters\DatabaseSeoSuggestionPersister;
use App\SeoBridge\Repositories\EmbeddableContentRepository;
use App\SeoBridge\Repositories\MysqlSemanticLinkRepository;
use App\SeoBridge\Repositories\MysqlSeoPageRepository;
use App\SeoBridge\SearchConsole\SearchConsoleHistoricalImporter;
use App\SeoPresets\Amiantix\AmiantixBlueprintProvider;
use App\SeoPresets\Amiantix\AmiantixContentProfile;
use App\SeoPresets\Amiantix\AmiantixImagePromptProvider;
use App\SeoPresets\Amiantix\AmiantixInternalLinkProvider;
use App\SeoPresets\Amiantix\AmiantixPromptProfile;
use Ofyre\SeoEngine\Contracts\EmbeddableContentRepository as EmbeddableContentRepositoryContract;
use Ofyre\SeoEngine\Contracts\HistoricalSeoImporter;
use Ofyre\SeoEngine\Contracts\ImagePromptProvider;
use Ofyre\SeoEngine\Contracts\InternalLinkProvider;
use Ofyre\SeoEngine\Contracts\NicheBlueprintProvider;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Ofyre\SeoEngine\Contracts\SemanticLinkRepository;
use Ofyre\SeoEngine\Contracts\SeoAuditPersister;
use Ofyre\SeoEngine\Contracts\SeoFeedbackLoopDriver;
use Ofyre\SeoEngine\Contracts\SeoGenerationDriver;
use Ofyre\SeoEngine\Contracts\SeoPageRepository;
use Ofyre\SeoEngine\Contracts\SeoSuggestionPersister;
use Ofyre\SeoEngine\Contracts\VectorStore;

$this->app->bind(SeoPageRepository::class, MysqlSeoPageRepository::class);
$this->app->bind(SeoGenerationDriver::class, OpenAiSeoGenerationDriver::class);
$this->app->bind(SeoFeedbackLoopDriver::class, DatabaseSeoFeedbackLoopDriver::class);
$this->app->bind(HistoricalSeoImporter::class, SearchConsoleHistoricalImporter::class);
$this->app->bind(EmbeddableContentRepositoryContract::class, EmbeddableContentRepository::class);
$this->app->bind(SemanticLinkRepository::class, MysqlSemanticLinkRepository::class);
$this->app->bind(VectorStore::class, MysqlVectorStore::class);
$this->app->bind(SeoAuditPersister::class, DatabaseSeoAuditPersister::class);
$this->app->bind(SeoSuggestionPersister::class, DatabaseSeoSuggestionPersister::class);

$this->app->bind(NicheBlueprintProvider::class, AmiantixBlueprintProvider::class);
$this->app->bind(PromptProfileProvider::class, AmiantixPromptProfile::class);
$this->app->bind(NicheContentProvider::class, AmiantixContentProfile::class);
$this->app->bind(InternalLinkProvider::class, AmiantixInternalLinkProvider::class);
$this->app->bind(ImagePromptProvider::class, AmiantixImagePromptProvider::class);
```

## 6. Preset Amiantix

Le package fournit deja un scaffold pret a l emploi dans :

- `examples/AmiantixPreset/AmiantixBlueprintProvider.php`
- `examples/AmiantixPreset/AmiantixPromptProfile.php`
- `examples/AmiantixPreset/AmiantixContentProfile.php`
- `examples/AmiantixPreset/AmiantixInternalLinkProvider.php`
- `examples/AmiantixPreset/AmiantixImagePromptProvider.php`

Vous pouvez soit les utiliser directement, soit les recopier dans `app/SeoPresets/Amiantix/`.

## 7. API privee

Dans `routes/api.php`, exposer au minimum :

- `POST /api/seo/generate`
- `POST /api/seo/rewrite`
- `POST /api/seo/analyze`
- `GET /api/seo/opportunities`
- `POST /api/seo/autopilot`
- `GET /api/seo/pages`
- `GET /api/seo/search-console`
- `GET /api/seo/indexation`
- `GET /api/seo/internal-links`

Middleware recommande :

- `EnsureSeoEngineToken`
- validation `Authorization: Bearer <token>`
- rate limiting
- journalisation des appels
- allowlist IP si le flux Amiantix est stable

## 8. Search Console, workers et scheduler

Apres les bindings :

```bash
php artisan seo:doctor
php artisan optimize:clear
php artisan seo:import-history
php artisan seo:embed-content --force
php artisan seo:semantic-links
php artisan seo:detect-cannibalization
php artisan seo:match-query-pages
```

Cron :

```bash
cd /var/www/seo-engine-app && php artisan schedule:run >> /dev/null 2>&1
```

Worker Supervisor :

```bash
php artisan queue:work
```

## 9. Priorite de mise en oeuvre

Ordre conseille pour faire passer le runtime au vert :

1. preset Amiantix
2. `SeoPageRepository`
3. `SeoGenerationDriver`
4. `SeoAuditPersister`
5. `SeoFeedbackLoopDriver`
6. `HistoricalSeoImporter`
7. `EmbeddableContentRepository`
8. `VectorStore`
9. `SemanticLinkRepository`
10. endpoints API prives

Quand ces pieces sont en place, `seo-engine-app` devient le vrai microservice autonome, et Symfony Amiantix ne garde que le role d orchestrateur UI/API.
