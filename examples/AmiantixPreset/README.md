# Amiantix Preset

Ce dossier fournit un preset metier exploitable pour un runtime Laravel Amiantix.

Il couvre les briques editoriales suivantes :

- `AmiantixBlueprintProvider`
- `AmiantixPromptProfile`
- `AmiantixContentProfile`
- `AmiantixInternalLinkProvider`
- `AmiantixImagePromptProvider`

## Clusters integres

- `ss3`
- `ss4`
- `desamiantage`
- `confinement`
- `dta`
- `reglementation`
- `copropriete`
- `reperage`
- `empoussierement`
- `diagnostics`

## Configuration exemple

```php
'contracts' => [
    'niche_blueprint_provider' => \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixBlueprintProvider::class,
    'prompt_profile_provider' => \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixPromptProfile::class,
    'niche_content_provider' => \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixContentProfile::class,
    'internal_link_provider' => \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixInternalLinkProvider::class,
    'image_prompt_provider' => \Ofyre\SeoEngine\Examples\AmiantixPreset\AmiantixImagePromptProvider::class,
],
```

## Comment l utiliser

1. Utiliser ce preset pour debloquer `seo:doctor` sur la partie niche/prompt/content.
2. Ajouter ensuite dans l app runtime les bridges concrets DB, OpenAI, Search Console et vector store.
3. Remplacer progressivement les textes fallback par les regles editoriales Amiantix definitives.

Ce preset regle la couche metier. Il ne remplace pas les adapters runtime comme `SeoPageRepository`, `HistoricalSeoImporter`, `SeoGenerationDriver` ou `VectorStore`.
