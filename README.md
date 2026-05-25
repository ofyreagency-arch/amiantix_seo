# Ofyre SEO Engine

Reusable AI SEO engine for Laravel applications.

This repository is the reusable engine core, not the final host runtime. For production, install it inside a dedicated Laravel app that exposes the private API, persistence, workers and scheduler.

Runtime product/runtime decisions for the multi-site host app are documented here:

[docs/runtime-product-design.md](./docs/runtime-product-design.md)

Current runtime capability status and real-world validation roadmap are documented here:

[docs/runtime-capability-matrix.md](./docs/runtime-capability-matrix.md)

Current product stabilization plan is documented here:

[docs/product-stabilization-plan.md](./docs/product-stabilization-plan.md)

Current editorial workflow and preset audit are documented here:

[docs/editorial-workflow-audit.md](./docs/editorial-workflow-audit.md)

Current bridge/connectivity architecture for client sites is documented here:

[docs/bridge-connectivity-architecture.md](./docs/bridge-connectivity-architecture.md)

## What lives in the package

- SEO generation core
- rewrite engine
- scoring and quality gates
- Search Console integration
- monitoring and feedback loop runners
- scheduler registration
- cockpit/status core services
- Artisan commands

## Official bridge packages

The central engine is meant to connect to light client-side bridges.

Current first-party bridge work lives here:

- Symfony bridge package: [bridges/symfony-bridge](./bridges/symfony-bridge)
- Laravel bridge is still being hardened from the example workflow

The package core is intentionally niche-agnostic. Business-specific behavior should be provided by adapters.

## Install in a Laravel app

```bash
composer require ofyre/seo-engine
php artisan seo:install
```

## Required adapters

Configure the contracts in `config/seo-engine.php`:

```php
'contracts' => [
    'niche_blueprint_provider' => App\Services\MyBlueprintProvider::class,
    'prompt_profile_provider' => App\Services\MyPromptProfile::class,
    'niche_content_provider' => App\Services\MyContentProfile::class,
    'internal_link_provider' => App\Services\MyInternalLinkProvider::class,
    'image_prompt_provider' => App\Services\MyImagePromptProvider::class,
    'search_console_token_provider' => App\Services\MySearchConsoleTokenProvider::class,
    'seo_page_repository' => App\Services\MySeoPageRepository::class,
    'seo_audit_persister' => App\Services\MySeoAuditPersister::class,
    'seo_generation_driver' => App\Services\MySeoGenerationDriver::class,
    'seo_feedback_loop_driver' => App\Services\MySeoFeedbackLoopDriver::class,
],
```

The package service provider auto-binds configured classes when they exist.

## First health check

Run:

```bash
php artisan seo:doctor
```

This checks:

- site config
- scheduler config
- required contract wiring
- OpenAI availability
- Search Console activation

## Installer helper

Run:

```bash
php artisan seo:install
```

Useful options:

```bash
php artisan seo:install --dry-run
php artisan seo:install --force
```

`seo:install` publishes the package config, prints the required wiring steps, then runs `seo:doctor`.

## Semantic embeddings

Phase 1 semantic support is intentionally lightweight and optional.

Enable it with:

```bash
SEO_EMBEDDINGS_ENABLED=true
```

Main commands:

```bash
php artisan seo:embed-content --force
php artisan seo:semantic-links
php artisan seo:detect-cannibalization
php artisan seo:match-query-pages
php artisan seo:queue-signal-suggestions
```

Current phase 1 use cases:

- page embeddings
- semantic internal-link suggestions
- hash-based refresh to avoid unnecessary embedding calls
- pgvector-ready storage in Postgres with JSON fallback for other databases
- optional semantic link policy layer for cluster and intent-aware filtering
- semantic cannibalization detection with editorial recommendation labels
- Search Console query embeddings and query/page opportunity matching

If `pgvector` is not installed on the host, migrations still succeed and embeddings are stored in JSON only.

In the admin cockpit, the page edit view exposes:

- semantic neighbors
- internal-link suggestions
- similarity scores
- cannibalization risks
- query opportunities
- nearby clusters
- pending suggestions queue fed by semantic and query signals

This makes the semantic layer observable while you add query/page matching on top.

## Semantic policy layer

The engine can keep embeddings broad while applying a narrower editorial filter on top.

Use a `SemanticLinkPolicyProvider` to:

- boost same-cluster neighbors
- boost same-intent neighbors
- penalize cross-cluster business pages
- penalize overly generic targets
- limit suggestions that are already strongly linked

That keeps the engine useful for real internal linking instead of returning only mathematically close pages.

## Cannibalization recommendation layer

The engine keeps the similarity detection separate from the recommendation label.

That allows a host app to keep the semantic detection broad while making the action advice more conservative and editorial:

- `differentiate_angle`
- `clarify_search_intent`
- `review_cluster_overlap`
- `consolidate_weaker_page`
- `monitor_overlap`

In practice, `consolidate_weaker_page` should stay rare and be reserved for very strong, same-intent overlap with clearly uneven visibility signals.

## Query/page matching

The next semantic layer connects Search Console queries to the page that currently serves them, or the page that should likely serve them better.

Main command:

```bash
php artisan seo:match-query-pages
```

This stores query opportunities in the semantic store and exposes them in the admin cockpit with editorial actions such as:

- `refresh_existing_page`
- `review_wrong_ranking_page`
- `differentiate_existing_page`
- `create_dedicated_page`
- `review_query_cluster`
- `monitor_query`

The engine stays conservative here: `create_dedicated_page` is reserved for stronger query signals, while weaker or fuzzier opportunities are kept in lighter review states.

## Signal suggestion queue

The next workflow layer turns signals into pending changes that a human can approve or reject.

Main command:

```bash
php artisan seo:queue-signal-suggestions
```

This command reads:

- semantic internal-link suggestions
- cannibalization risks
- query/page opportunities

and turns them into pending `SeoChangeSuggestion` items for the admin queue.

The intended workflow is:

1. detect signals
2. generate a safe suggestion draft
3. review in admin
4. approve or reject
5. apply and monitor

This keeps the engine proactive without switching to aggressive auto-publication.

## Night scheduler flow

The default scheduler can now run the semantic pipeline automatically during the night:

1. `seo:semantic-links`
2. `seo:detect-cannibalization`
3. `seo:match-query-pages`
4. `seo:queue-signal-suggestions`

That means the admin queue can be refreshed before the team logs in, with suggestions already waiting for approval or rejection.

## Example preset

A generic starter preset is included here:

[examples/GenericBusinessPreset/README.md](./examples/GenericBusinessPreset/README.md)

Use it as a scaffold when starting a new niche before you build a richer custom preset.
