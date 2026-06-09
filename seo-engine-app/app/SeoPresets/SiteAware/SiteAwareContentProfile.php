<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\NicheContentProvider;

final class SiteAwareContentProfile implements NicheContentProvider
{
    public function fallbackPayload(string $keyword, string $cluster, array $blueprint, array $context = []): array
    {
        throw new \RuntimeException('La génération de secours générique est désactivée. Utilisez la génération IA guidée par le profil métier.');
    }

    public function extraSection(string $keyword, array $blueprint, array $context = []): string
    {
        $profile = SiteProfilePromptContext::profile() ?? [];
        $services = is_array($profile['services'] ?? null) ? $profile['services'] : [];
        $service = $services[0] ?? null;

        if (! is_array($service)) {
            return '';
        }

        $name = (string) ($service['name'] ?? '');
        $description = (string) ($service['description'] ?? '');

        if ($name === '') {
            return '';
        }

        return '<h2>'.e($name).' et '.$keyword.'</h2>'
            .'<p>'.e(Str::limit($description, 320)).'</p>';
    }

    public function ensureContentDepth(string $content, array $blueprint, array $context = []): string
    {
        if ($context['preserve_ai_narrative'] ?? false) {
            return $content;
        }

        $wordCount = str_word_count(strip_tags($content));

        if ($wordCount >= 900) {
            return $content;
        }

        $extra = $this->extraSection((string) ($context['keyword'] ?? ''), $blueprint, $context);

        if ($extra === '') {
            return $content;
        }

        return rtrim($content)."\n".$extra;
    }
}
