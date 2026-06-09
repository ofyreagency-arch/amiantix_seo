<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use App\SeoPresets\Shared\FieldExpertWritingDirectives;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

final class SiteAwarePromptProfile implements PromptProfileProvider
{
    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return $this->generationCorePrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals);
    }

    public function generationCorePrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        $directives = data_get(SiteProfilePromptContext::profile(), 'generation_directives', []);
        $language = (string) ($directives['language'] ?? 'fr');
        $siteName = (string) ($directives['site_name'] ?? config('seo-engine.site.name', 'le site'));

        $langLine = $language === 'fr'
            ? "Tu rédiges un article métier en français pour {$siteName}. Le lecteur est un professionnel du secteur, pas un lecteur SEO."
            : "You write a field article in English for {$siteName}. The reader is an industry professional, not an SEO audience.";

        return $langLine."\n"
            .SiteProfilePromptContext::block()."\n"
            .FieldExpertWritingDirectives::promptBlock($language)."\n"
            .'Mot-clé principal : '.$keyword."\n"
            .'Cluster : '.$cluster."\n"
            .'Sujet : '.($blueprint['topic'] ?? $keyword)."\n"
            .'Angle : '.($blueprint['hero_angle'] ?? '')."\n"
            .'Fil narratif à couvrir (ne pas copier ces intitulés comme H2 — les traduire en situations métier concrètes) : '
            .json_encode($editorialSections, JSON_UNESCAPED_UNICODE)."\n"
            .'Signaux métier à faire passer naturellement : '.json_encode($expectedSignals, JSON_UNESCAPED_UNICODE)."\n"
            .'Cas terrain à intégrer (adapter, ne pas copier mot pour mot) : '.json_encode($blueprint['cases'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            .'Erreurs fréquentes à traiter : '.json_encode($blueprint['mistakes'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            .'Scénarios chantier / intervention : '.json_encode($blueprint['field_scenarios'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            .'Arbitrages client à expliciter : '.json_encode($blueprint['arbitrages'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            ."Forme attendue :\n"
            ."- un seul article continu, voix d'expert terrain\n"
            ."- H2/H3 avec titres métier concrets (jamais numérotés, jamais \"Zoom terrain\", jamais \"Guide\")\n"
            ."- narration d'abord ; listes courtes seulement après un paragraphe de contexte\n"
            ."- pas de tableau, pas de FAQ dans le corps, pas de section \"ressources\"\n"
            ."- title et meta_description factuels, sans \"découvrez\" ni \"notre guide\"\n"
            ."- 1000 mots minimum dans content\n"
            ."- content en HTML : <h2> pour les sections, <p> pour les paragraphes (pas de texte brut)\n"
            .'Retourner uniquement un JSON avec : title, meta_description, h1, content.';
    }

    public function generationFaqPrompt(string $keyword, string $cluster, array $blueprint, string $title, string $metaDescription, string $h1, string $content): string
    {
        $language = (string) data_get(SiteProfilePromptContext::profile(), 'generation_directives.language', 'fr');

        return ($language === 'fr'
            ? "Tu complètes éventuellement une FAQ courte pour cet article métier.\n"
            : "You may add a short FAQ for this field article.\n")
            .SiteProfilePromptContext::block()."\n"
            .FieldExpertWritingDirectives::promptBlock($language)."\n"
            .'Mot-clé : '.$keyword."\n"
            .'Titre : '.$title."\n"
            .'H1 : '.$h1."\n"
            ."Contraintes FAQ :\n"
            ."- 0 à 4 questions maximum ; retourner faq: [] si aucune question client naturelle\n"
            ."- questions formulées comme un vrai client au téléphone ou sur le chantier\n"
            ."- réponses courtes, concrètes, avec chiffre ou situation terrain si pertinent\n"
            ."- interdit : questions sur l'article, le SEO, la \"profondeur du contenu\" ou le blog\n"
            ."Contenu principal :\n".$content."\n"
            .'Retourner uniquement un JSON avec : faq.';
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return "Améliore cet article avec plus de profondeur métier et d'expertise terrain.\n"
            .SiteProfilePromptContext::block()."\n"
            .FieldExpertWritingDirectives::promptBlock()."\n"
            .'Mot-clé : '.($page->keyword ?? '')."\n"
            .'Problèmes : '.json_encode($audit['issues'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            .'Fil narratif : '.json_encode($editorialSections, JSON_UNESCAPED_UNICODE)."\n"
            .'Cas terrain : '.json_encode($blueprint['cases'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            ."Conserver une seule voix rédactionnelle. Supprimer tableaux, listes répétitives et structures SEO visibles.\n"
            .'Retourner uniquement un JSON avec : title, meta_description, h1, content, faq, schema.';
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return "Réécris cet article en mode {$mode} avec une expertise terrain renforcée.\n"
            .SiteProfilePromptContext::block()."\n"
            .FieldExpertWritingDirectives::promptBlock()."\n"
            .'Mot-clé : '.($page->keyword ?? '')."\n"
            .'Contenu actuel : '.Str::limit(strip_tags((string) ($page->content ?? '')), 4000)."\n"
            ."Objectif : une seule narration continue avec situations réelles, exemples chiffrés, erreurs terrain, conséquences et arbitrages client.\n"
            ."Interdit : blocs collés, Zoom terrain N, Exemple N, tableaux systématiques, FAQ dans le corps.\n"
            .'Retourner uniquement un JSON avec : title, meta_description, h1, content, faq, schema.';
    }

    /**
     * @return array<string,mixed>
     */
    public function fallbackRewrite(object $page, string $mode): array
    {
        throw new \RuntimeException('La réécriture de secours générique est désactivée. Le profil métier du site est requis.');
    }
}
