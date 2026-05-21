<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\SeoPage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Services\Generation\SeoGenerationService;
use Ofyre\SeoEngine\Services\Scoring\SeoScoreRefreshService;
use RuntimeException;

class SeoPageImageGenerator
{
    public function __construct(
        private readonly SeoGenerationService $generation,
        private readonly SeoScoreRefreshService $scoreRefresh,
    ) {}

    public function generate(SeoPage $page): SeoPage
    {
        $apiKey = (string) config('services.openai.api_key', '');
        if ($apiKey === '') {
            throw new RuntimeException('OPENAI_API_KEY manquante pour la génération d’image.');
        }

        $prompt = trim((string) ($page->image_prompt ?: $this->generation->generateImagePrompt($page->keyword, $page->cluster)));
        if ($prompt === '') {
            throw new RuntimeException('Aucun prompt image disponible pour cette page.');
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->connectTimeout((int) config('services.openai.connect_timeout', 30))
                ->timeout((int) config('services.openai.request_timeout', 180))
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => (string) config('services.openai.image_model', 'gpt-image-1'),
                    'prompt' => $prompt,
                    'size' => '1536x1024',
                    'quality' => 'high',
                    'response_format' => 'b64_json',
                ]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Connexion OpenAI impossible pendant la génération d’image: '.$exception->getMessage(), previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI a refusé la génération d’image (HTTP '.$response->status().').');
        }

        $base64 = $response->json('data.0.b64_json');
        if (! is_string($base64) || trim($base64) === '') {
            throw new RuntimeException('Réponse image OpenAI incomplète.');
        }

        $binary = base64_decode($base64, true);
        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Image OpenAI illisible après décodage.');
        }

        $relativePath = sprintf(
            'seo-pages/%s/%s-%s.png',
            $page->site_id,
            Str::slug($page->slug ?: $page->keyword),
            now()->format('YmdHis')
        );

        Storage::disk('public')->put($relativePath, $binary);

        $page->forceFill([
            'image_prompt' => $prompt,
            'image_path' => $relativePath,
            'image_alt' => $this->altFor($page),
            'image_status' => 'generated',
            'image_quality_json' => [
                'review_state' => 'generated',
                'generation' => [
                    'provider' => 'openai',
                    'model' => (string) config('services.openai.image_model', 'gpt-image-1'),
                    'generated_at' => now()->toIso8601String(),
                    'path' => $relativePath,
                ],
            ],
        ])->save();

        return $this->scoreRefresh->refresh($page->refresh());
    }

    public function approve(SeoPage $page): SeoPage
    {
        if (! filled($page->image_path)) {
            throw new RuntimeException('Impossible d’approuver une image absente.');
        }

        $quality = is_array($page->image_quality_json) ? $page->image_quality_json : [];
        $quality['review_state'] = 'approved';

        $page->forceFill([
            'image_status' => 'approved',
            'image_quality_json' => $quality,
        ])->save();

        return $this->scoreRefresh->refresh($page->refresh());
    }

    private function altFor(SeoPage $page): string
    {
        $base = trim((string) ($page->title ?: $page->keyword));

        return $base !== '' ? $base : 'Illustration SEO';
    }
}
