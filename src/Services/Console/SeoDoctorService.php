<?php

declare(strict_types=1);

namespace Ofyre\SeoEngine\Services\Console;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Arr;

class SeoDoctorService
{
    /**
     * @return array{
     *     ok:bool,
     *     checks:array<int,array{status:string,label:string,details:string}>,
     *     warnings:array<int,string>,
     *     errors:array<int,string>
     * }
     */
    public function inspect(Application $app): array
    {
        $checks = [];
        $warnings = [];
        $errors = [];

        $checks[] = $this->checkSiteConfig();
        $checks[] = $this->checkSchedulerConfig();

        foreach ($this->contractChecks($app) as $check) {
            $checks[] = $check;
        }

        if (! config('services.openai.api_key')) {
            $warnings[] = 'OPENAI_API_KEY is not configured. AI generation and rewrite calls will fallback or be skipped.';
        }

        if (! config('seo-engine.search_console.enabled', false)) {
            $warnings[] = 'Search Console is disabled. Historical import and inspection features will stay inactive.';
        }

        if (! config('seo-engine.embeddings.enabled', false)) {
            $warnings[] = 'Semantic embeddings are disabled. Vector-based internal links and cannibalization checks will stay inactive.';
        }

        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $errors[] = $check['label'].': '.$check['details'];
            }
        }

        return [
            'ok' => $errors === [],
            'checks' => $checks,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{status:string,label:string,details:string}
     */
    private function checkSiteConfig(): array
    {
        $url = trim((string) config('seo-engine.site.url', ''));
        $locale = trim((string) config('seo-engine.site.locale', ''));
        $niche = trim((string) config('seo-engine.site.niche', ''));

        if ($url === '' || $locale === '' || $niche === '') {
            return [
                'status' => 'error',
                'label' => 'Site config',
                'details' => 'Missing one of seo-engine.site.url, seo-engine.site.locale or seo-engine.site.niche.',
            ];
        }

        return [
            'status' => 'ok',
            'label' => 'Site config',
            'details' => sprintf('url=%s locale=%s niche=%s', $url, $locale, $niche),
        ];
    }

    /**
     * @return array{status:string,label:string,details:string}
     */
    private function checkSchedulerConfig(): array
    {
        $enabled = (bool) config('seo-engine.scheduler.enabled', true);
        $commands = Arr::wrap(config('seo-engine.scheduler.commands', []));

        if (! $enabled) {
            return [
                'status' => 'warning',
                'label' => 'Scheduler',
                'details' => 'Scheduler registration is disabled in seo-engine.scheduler.enabled.',
            ];
        }

        if ($commands === []) {
            return [
                'status' => 'error',
                'label' => 'Scheduler',
                'details' => 'No scheduler commands are configured.',
            ];
        }

        return [
            'status' => 'ok',
            'label' => 'Scheduler',
            'details' => sprintf('%d scheduled SEO command(s) configured.', count($commands)),
        ];
    }

    /**
     * @return array<int,array{status:string,label:string,details:string}>
     */
    private function contractChecks(Application $app): array
    {
        $contracts = [
            'historical_importer' => 'Historical importer',
            'image_prompt_provider' => 'Image prompt provider',
            'internal_link_provider' => 'Internal link provider',
            'niche_blueprint_provider' => 'Blueprint provider',
            'niche_content_provider' => 'Content profile provider',
            'prioritized_page_provider' => 'Prioritized page provider',
            'prompt_profile_provider' => 'Prompt profile provider',
            'rewrite_access_decider' => 'Rewrite access decider',
            'seo_audit_persister' => 'SEO audit persister',
            'seo_feedback_loop_driver' => 'Feedback loop driver',
            'seo_generation_driver' => 'SEO generation driver',
            'seo_page_repository' => 'SEO page repository',
        ];

        if (config('seo-engine.embeddings.enabled', false)) {
            $contracts = array_merge([
                'embedding_provider' => 'Embedding provider',
                'embeddable_content_repository' => 'Embeddable content repository',
                'semantic_link_repository' => 'Semantic link repository',
                'vector_store' => 'Vector store',
            ], $contracts);
        }

        $checks = [];

        foreach ($contracts as $key => $label) {
            $configured = config('seo-engine.contracts.'.$key);

            if (! is_string($configured) || $configured === '') {
                $checks[] = [
                    'status' => 'error',
                    'label' => $label,
                    'details' => 'No class configured in seo-engine.contracts.'.$key.'.',
                ];
                continue;
            }

            if (! class_exists($configured)) {
                $checks[] = [
                    'status' => 'error',
                    'label' => $label,
                    'details' => sprintf('Configured class %s does not exist.', $configured),
                ];
                continue;
            }

            if (! $app->bound($configured) && ! $app->bound($this->contractClassForKey($key))) {
                $checks[] = [
                    'status' => 'warning',
                    'label' => $label,
                    'details' => sprintf('Configured class %s exists but is not explicitly bound in the container.', $configured),
                ];
                continue;
            }

            $checks[] = [
                'status' => 'ok',
                'label' => $label,
                'details' => $configured,
            ];
        }

        return $checks;
    }

    private function contractClassForKey(string $key): string
    {
        return match ($key) {
            'embedding_provider' => \Ofyre\SeoEngine\Contracts\EmbeddingProvider::class,
            'embeddable_content_repository' => \Ofyre\SeoEngine\Contracts\EmbeddableContentRepository::class,
            'historical_importer' => \Ofyre\SeoEngine\Contracts\HistoricalSeoImporter::class,
            'image_prompt_provider' => \Ofyre\SeoEngine\Contracts\ImagePromptProvider::class,
            'internal_link_provider' => \Ofyre\SeoEngine\Contracts\InternalLinkProvider::class,
            'niche_blueprint_provider' => \Ofyre\SeoEngine\Contracts\NicheBlueprintProvider::class,
            'niche_content_provider' => \Ofyre\SeoEngine\Contracts\NicheContentProvider::class,
            'prioritized_page_provider' => \Ofyre\SeoEngine\Contracts\PrioritizedPageProvider::class,
            'prompt_profile_provider' => \Ofyre\SeoEngine\Contracts\PromptProfileProvider::class,
            'rewrite_access_decider' => \Ofyre\SeoEngine\Contracts\RewriteAccessDecider::class,
            'seo_audit_persister' => \Ofyre\SeoEngine\Contracts\SeoAuditPersister::class,
            'seo_feedback_loop_driver' => \Ofyre\SeoEngine\Contracts\SeoFeedbackLoopDriver::class,
            'seo_generation_driver' => \Ofyre\SeoEngine\Contracts\SeoGenerationDriver::class,
            'seo_page_repository' => \Ofyre\SeoEngine\Contracts\SeoPageRepository::class,
            'semantic_link_repository' => \Ofyre\SeoEngine\Contracts\SemanticLinkRepository::class,
            'vector_store' => \Ofyre\SeoEngine\Contracts\VectorStore::class,
            default => '',
        };
    }
}
