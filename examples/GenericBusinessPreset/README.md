# Generic Business Preset

This folder contains a niche-agnostic example preset for `ofyre/seo-engine`.

It is not meant to be your final production preset. It is a starter kit that shows how to wire:

- `NicheBlueprintProvider`
- `PromptProfileProvider`
- `NicheContentProvider`
- `InternalLinkProvider`
- `ImagePromptProvider`

## Classes

- `GenericBusinessBlueprintProvider`
- `GenericBusinessPromptProfile`
- `GenericBusinessContentProfile`
- `GenericBusinessInternalLinkProvider`
- `GenericBusinessImagePromptProvider`

## Example config

```php
'contracts' => [
    'niche_blueprint_provider' => \Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessBlueprintProvider::class,
    'prompt_profile_provider' => \Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessPromptProfile::class,
    'niche_content_provider' => \Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessContentProfile::class,
    'internal_link_provider' => \Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessInternalLinkProvider::class,
    'image_prompt_provider' => \Ofyre\SeoEngine\Examples\GenericBusinessPreset\GenericBusinessImagePromptProvider::class,
],
```

## How to use it

1. Start with these classes in a test Laravel app.
2. Run `php artisan seo:install`.
3. Run `php artisan seo:doctor`.
4. Generate a first page and inspect the output.
5. Replace the generic business vocabulary with your niche vocabulary.

## For real projects

Treat this preset as a scaffold for your own niche:

- keep the structure
- replace the topic vocabulary
- replace the internal linking map
- replace the prompt tone
- replace the fallback content blocks
