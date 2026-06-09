<?php

declare(strict_types=1);

namespace App\SeoPresets\SiteAware;

use Illuminate\Support\Str;
use Ofyre\SeoEngine\Contracts\PromptProfileProvider;

final class SiteAwarePromptProfile implements PromptProfileProvider
{
    public function generationPrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        return $this->generationCorePrompt($keyword, $cluster, $blueprint, $editorialSections, $expectedSignals)."\n"
            .'Puis compléter aussi faq et schema si possible.';
    }

    public function generationCorePrompt(string $keyword, string $cluster, array $blueprint, array $editorialSections, array $expectedSignals): string
    {
        $directives = data_get(SiteProfilePromptContext::profile(), 'generation_directives', []);
        $language = (string) ($directives['language'] ?? 'fr');
        $siteName = (string) ($directives['site_name'] ?? config('seo-engine.site.name', 'le site'));

        $langLine = $language === 'fr'
            ? "Tu rédiges un article expert en français pour le site {$siteName}."
            : "You write an expert article in English for {$siteName}.";

        return $langLine."\n"
            .SiteProfilePromptContext::block()."\n"
            .'Mot-clé principal : '.$keyword."\n"
            .'Cluster : '.$cluster."\n"
            .'Sujet : '.($blueprint['topic'] ?? $keyword)."\n"
            .'Angle : '.($blueprint['hero_angle'] ?? '')."\n"
            .'Sections éditoriales attendues : '.json_encode($editorialSections, JSON_UNESCAPED_UNICODE)."\n"
            .'Signaux métier obligatoires : '.json_encode($expectedSignals, JSON_UNESCAPED_UNICODE)."\n"
            .'Services à citer : '.json_encode($blueprint['services'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            ."Contraintes éditoriales (expert terrain) :\n"
            ."- ton expert, concret, ancré dans le métier réel du site\n"
            ."- privilégier des cas réels ou scénarios réalistes plutôt que des listes génériques\n"
            ."- inclure au moins 2 exemples chiffrés crédibles (délais, volumes, budgets, surfaces, effectifs, fréquences)\n"
            ."- décrire des erreurs fréquentes observées sur le terrain et leurs conséquences concrètes\n"
            ."- montrer des arbitrages client (coût vs risque, urgence vs conformité, interne vs prestataire)\n"
            ."- utiliser des exemples métier propres à la niche et au vocabulaire fourni\n"
            ."- aucun template SaaS, aucune section fictive, aucun placeholder\n"
            ."- relier le sujet aux services et pages réelles du site\n"
            ."- 1200 mots minimum, avec paragraphes narratifs plutôt que des bullet lists vides\n"
            .'Retourner uniquement un JSON avec : title, meta_description, h1, content.';
    }

    public function generationFaqPrompt(string $keyword, string $cluster, array $blueprint, string $title, string $metaDescription, string $h1, string $content): string
    {
        $language = (string) data_get(SiteProfilePromptContext::profile(), 'generation_directives.language', 'fr');

        return ($language === 'fr'
            ? "Tu complètes uniquement la FAQ d'un article métier pour ce site.\n"
            : "You complete only the FAQ for a business article on this site.\n")
            .SiteProfilePromptContext::block()."\n"
            .'Mot-clé : '.$keyword."\n"
            .'Titre : '.$title."\n"
            .'H1 : '.$h1."\n"
            ."Contraintes FAQ :\n"
            ."- 5 questions minimum, formulées comme de vraies questions clients\n"
            ."- réponses concrètes avec exemples chiffrés ou situations terrain quand pertinent\n"
            ."- mentionner erreurs fréquentes et conséquences si utile\n"
            ."Contenu principal :\n".$content."\n"
            .'Retourner uniquement un JSON avec : faq.';
    }

    public function improvementPrompt(object $page, array $blueprint, array $audit, array $editorialSections, array $expectedSignals): string
    {
        return "Améliore cet article avec plus de profondeur métier et d'expertise terrain.\n"
            .SiteProfilePromptContext::block()."\n"
            .'Mot-clé : '.($page->keyword ?? '')."\n"
            .'Problèmes : '.json_encode($audit['issues'] ?? [], JSON_UNESCAPED_UNICODE)."\n"
            .'Sections attendues : '.json_encode($editorialSections, JSON_UNESCAPED_UNICODE)."\n"
            ."Ajouter ou renforcer :\n"
            ."- cas réels / scénarios réalistes\n"
            ."- exemples chiffrés crédibles\n"
            ."- erreurs fréquentes terrain et conséquences\n"
            ."- arbitrages client\n"
            ."- réduction des listes génériques\n"
            .'Retourner uniquement un JSON avec : title, meta_description, h1, content, faq, schema.';
    }

    public function rewritePrompt(object $page, string $mode): string
    {
        return "Réécris cet article en mode {$mode} avec une expertise terrain renforcée.\n"
            .SiteProfilePromptContext::block()."\n"
            .'Mot-clé : '.($page->keyword ?? '')."\n"
            .'Contenu actuel : '.Str::limit(strip_tags((string) ($page->content ?? '')), 4000)."\n"
            ."Objectif : plus de situations réelles, exemples chiffrés, erreurs terrain, conséquences et arbitrages client.\n"
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
