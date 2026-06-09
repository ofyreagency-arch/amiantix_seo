<?php

declare(strict_types=1);

namespace Tests\Unit;

use Praeviseo\SymfonyBridge\Service\NativePageHtmlPatcher;
use Tests\TestCase;

class NativePageHtmlPatcherTest extends TestCase
{
    public function test_applies_title_sections_and_faq_without_duplicating_existing_headings(): void
    {
        $patcher = new NativePageHtmlPatcher();

        $live = <<<'HTML'
<html><head><title>Old title</title></head><body>
<main><h2>Section existante</h2><p>Contenu actuel.</p></main>
</body></html>
HTML;

        $patched = $patcher->apply($live, [
            'title' => 'Nouveau titre SEO',
            'meta_description' => 'Meta enrichie',
            'content_html' => '<h2>Section existante</h2><p>dup</p><h2>Section nouvelle</h2><p>Texte ajouté.</p>',
            'faq_json' => [
                ['question' => 'Question SEO ?', 'answer' => 'Réponse SEO.'],
            ],
        ]);

        $this->assertStringContainsString('<title>Nouveau titre SEO</title>', $patched);
        $this->assertStringContainsString('name="description" content="Meta enrichie"', $patched);
        $this->assertStringContainsString('data-praeviseo-native="1"', $patched);
        $this->assertStringContainsString('Section nouvelle', $patched);
        $this->assertStringNotContainsString('Section existante</h2><p>dup</p><h2>Section nouvelle', $patched);
        $this->assertStringContainsString('Question SEO ?', $patched);
        $this->assertStringContainsString('application/ld+json', $patched);
    }
}
