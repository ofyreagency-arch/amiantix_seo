<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Understanding\EditorialTopicClassifier;
use Tests\TestCase;

class EditorialTopicClassifierTest extends TestCase
{
    private EditorialTopicClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = app(EditorialTopicClassifier::class);
    }

    public function test_rejects_contact_cta_and_legal_page_titles(): void
    {
        $this->assertFalse($this->classifier->isSearchableEditorialTopic('Parlons de vos dossiers techniques'));
        $this->assertFalse($this->classifier->isSearchableEditorialTopic('Politique de confidentialité RGPD'));
        $this->assertFalse($this->classifier->isSearchableEditorialTopic('Restez informé de l actualité amiante'));
        $this->assertFalse($this->classifier->isSearchableEditorialTopic('Une chaîne technique complète pour vos dossiers amiante'));
    }

    public function test_accepts_real_field_topics(): void
    {
        $this->assertTrue($this->classifier->isSearchableEditorialTopic('diagnostic amiante avant travaux'));
        $this->assertTrue($this->classifier->isSearchableEditorialTopic('repérage amiante avant travaux'));
        $this->assertTrue($this->classifier->isSearchableEditorialTopic('Dépannage plomberie urgence'));
    }

    public function test_builds_problem_based_editorial_topics_from_profile(): void
    {
        $topics = $this->classifier->buildEditorialTopics([
            'business' => ['industry' => 'amiante'],
            'vocabulary' => ['core_terms' => ['diagnostic', 'repérage', 'dossiers', 'newsletter']],
            'services' => [
                [
                    'name' => 'Services ingénierie amiante SS3/SS4',
                    'headings' => ['Dimensionnez protections et extracteurs sur chantier'],
                ],
            ],
        ]);

        $joined = implode("\n", $topics);

        $this->assertStringContainsString('diagnostic amiante avant travaux', $joined);
        $this->assertStringContainsString('repérage avant travaux', $joined);
        $this->assertStringContainsString('erreurs courantes amiante', $joined);
        $this->assertNotContains('Parlons de vos dossiers techniques', $topics);
        $this->assertFalse(collect($topics)->contains(fn (string $topic): bool => str_contains(mb_strtolower($topic), 'newsletter')));
    }
}
