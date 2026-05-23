<?php

declare(strict_types=1);

namespace App\SeoPresets\Generic;

use Ofyre\SeoEngine\Contracts\PromptProfileProvider;
use Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessPromptProfile;

class GenericPromptProfile implements PromptProfileProvider
{
    private GenericBusinessPromptProfile $inner;

    public function __construct()
    {
        $this->inner = new GenericBusinessPromptProfile();
    }

    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return $this->inner->generationPrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals);
    }

    public function generationCorePrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return $this->inner->generationPrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals)
            ."\nRetourner uniquement un JSON avec: title, meta_description, h1, content.";
    }

    public function generationFaqPrompt(string $keyword, string $cluster, array $blueprint, string $title, string $metaDescription, string $h1, string $content): string
    {
        return "Tu completes uniquement la FAQ d un article SEO.\n"
            .'Mot-cle principal: '.$keyword."\n"
            .'Cluster: '.$cluster."\n"
            .'Titre: '.$title."\n"
            .'Meta description: '.$metaDescription."\n"
            .'H1: '.$h1."\n"
            ."Contenu principal:\n".$content."\n"
            ."Retourner uniquement un JSON avec: faq.\n"
            ."faq doit contenir au minimum 5 objets {question, answer} utiles, non promotionnels et cohérents avec le contenu.";
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return $this->inner->improvementPrompt($page, $blueprint, $audit, $editorialSections, $expectedSignals);
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return $this->inner->rewritePrompt($page, $mode);
    }

    public function fallbackRewrite(object $page, string $mode): array
    {
        return $this->inner->fallbackRewrite($page, $mode);
    }
}
