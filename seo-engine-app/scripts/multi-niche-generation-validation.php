<?php

declare(strict_types=1);

/**
 * Valide la génération multi-niches : voix, profondeur, différenciation et HTML publié.
 *
 * Usage :
 *   php scripts/multi-niche-generation-validation.php
 *   php scripts/multi-niche-generation-validation.php --niches=amiante,plomberie --skip-generate
 */

use App\Models\SeoPage;
use App\Models\SeoSite;
use App\Runtime\SeoEngineContext;
use App\SeoPresets\SiteAware\NicheDistinguishabilityAnalyzer;
use App\Services\Publication\PublishedContentValidationService;
use Illuminate\Support\Str;
use Ofyre\SeoEngine\Services\Console\SeoGeneratePageRunner;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['niches::', 'skip-generate', 'output::', 'lab-site::']);
$nichesRaw = trim((string) ($options['niches'] ?? ''));
$skipGenerate = array_key_exists('skip-generate', $options);
$labSiteId = trim((string) ($options['lab-site'] ?? 'niche-lab'));
$outputPath = trim((string) ($options['output'] ?? ''));

/** @var array<string,array<string,mixed>> $fixtures */
$fixtures = [
    'amiante' => [
        'keyword' => 'diagnostic amiante avant travaux',
        'profile' => nicheFixture('Amiantix Lab', 'amiante', 'https://amiantix.fr', [
            'summary' => 'Logiciel et ingénierie documentaire amiante SS3/SS4.',
            'services' => [['name' => 'Références réglementaires amiante', 'description' => 'Corpus SS3/SS4', 'intent' => 'MONEY_PAGE']],
            'terms' => ['amiante', 'ss3', 'ss4', 'dta', 'repérage', 'desamiantage', 'copropriété'],
        ]),
    ],
    'plomberie' => [
        'keyword' => 'fuite chauffe-eau copropriété',
        'profile' => nicheFixture('Plomb Express', 'plomberie', 'https://plomb-express.fr', [
            'summary' => 'Dépannage plomberie et fuites en copropriété.',
            'services' => [['name' => 'Dépannage urgence 24h', 'description' => 'Fuite et dégât des eaux', 'intent' => 'MONEY_PAGE']],
            'terms' => ['fuite', 'plombier', 'chauffe-eau', 'canalisation', 'coupure', 'syndic'],
        ]),
    ],
    'avocat' => [
        'keyword' => 'mise en demeure impayé loyer',
        'profile' => nicheFixture('Cabinet Rivière', 'droit immobilier', 'https://cabinet-riviere.fr', [
            'summary' => 'Cabinet d\'avocats en droit immobilier et baux commerciaux.',
            'services' => [['name' => 'Contentieux locatif', 'description' => 'Loyers impayés et résiliation', 'intent' => 'MONEY_PAGE']],
            'terms' => ['avocat', 'mise en demeure', 'tribunal', 'bail', 'honoraires', 'prescription'],
        ]),
    ],
    'immobilier' => [
        'keyword' => 'diagnostics obligatoires vente maison',
        'profile' => nicheFixture('Agence Lemaire', 'immobilier', 'https://agence-lemaire.fr', [
            'summary' => 'Agence immobilière spécialisée vente de maisons et appartements.',
            'services' => [['name' => 'Mandat de vente', 'description' => 'Estimation et commercialisation', 'intent' => 'MONEY_PAGE']],
            'terms' => ['mandat', 'vente', 'diagnostic', 'notaire', 'compromis', 'charges'],
        ]),
    ],
    'recrutement' => [
        'keyword' => 'processus entretien recrutement cadre',
        'profile' => nicheFixture('Talent Plus RH', 'recrutement', 'https://talent-plus.fr', [
            'summary' => 'Cabinet de recrutement cadres et managers.',
            'services' => [['name' => 'Recrutement cadre', 'description' => 'Sourcing et assessment', 'intent' => 'MONEY_PAGE']],
            'terms' => ['candidat', 'entretien', 'sourcing', 'onboarding', 'marque employeur', 'fiche de poste'],
        ]),
    ],
];

$selectedNiches = $nichesRaw !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $nichesRaw))))
    : array_keys($fixtures);

$site = ensureLabSite($labSiteId);
$runner = app(SeoGeneratePageRunner::class);
$publishedValidator = app(PublishedContentValidationService::class);
$results = [];
$fingerprints = [];

foreach ($selectedNiches as $niche) {
    if (! isset($fixtures[$niche])) {
        fwrite(STDERR, "Unknown niche: {$niche}\n");
        exit(1);
    }

    $fixture = $fixtures[$niche];
    $keyword = (string) $fixture['keyword'];
    $profile = (array) $fixture['profile'];
    $site->saveSiteProfile($profile);
    $site = $site->fresh();
    app(SeoEngineContext::class)->loadFromSite($site);

    $entry = [
        'niche' => $niche,
        'keyword' => $keyword,
        'detected_niche' => $profile['business']['industry'] ?? null,
        'status' => 'pending',
    ];

    if ($skipGenerate) {
        $entry['status'] = 'skipped';
        $results[] = $entry;
        continue;
    }

    $slug = 'niche-'.$niche.'-'.Str::slug(Str::lower($keyword));
    SeoPage::query()->where('site_id', $site->site_id)->where('slug', $slug)->delete();

    try {
        $generated = $runner->run($keyword, 'draft', false);
        $page = $generated['page'];
        $content = (string) ($page->content ?? '');

        $page->forceFill([
            'slug' => $slug,
            'status' => 'published',
            'published_at' => now(),
        ])->save();
        $page = $page->refresh();

        $published = $publishedValidator->validate($site->fresh(), $page->fresh(), publishIfNeeded: true);
        $fingerprints[$niche] = NicheDistinguishabilityAnalyzer::fingerprint($niche, $content, [$keyword]);

        $entry = array_merge($entry, [
            'status' => ($published['ok'] ?? false) ? 'validated' : 'published_validation_failed',
            'slug' => $slug,
            'title' => $page->title,
            'generation_source' => $page->generation_source,
            'draft_word_count' => str_word_count(strip_tags($content)),
            'published_validation' => $published,
            'fingerprint' => $fingerprints[$niche],
            'live_url' => $page->live_url,
        ]);
    } catch (Throwable $exception) {
        $entry['status'] = 'error';
        $entry['error'] = $exception->getMessage();
    }

    $results[] = $entry;
    echo '== Niche: '.$niche.' =='."\n";
}

$comparison = $fingerprints !== [] ? NicheDistinguishabilityAnalyzer::compare($fingerprints) : null;

$report = [
    'validated_at' => now()->toIso8601String(),
    'lab_site_id' => $site->site_id,
    'niches' => $selectedNiches,
    'results' => $results,
    'distinguishability' => $comparison,
    'multi_niche_ok' => $comparison['distinct_enough'] ?? false,
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo $json."\n";

if ($outputPath !== '') {
    file_put_contents($outputPath, $json);
}

$failed = collect($results)->contains(fn (array $item): bool => ! in_array($item['status'] ?? '', ['validated', 'skipped'], true))
    || ($comparison !== null && ! ($comparison['distinct_enough'] ?? false));

exit($failed ? 3 : 0);

/**
 * @param  array<string,mixed>  $data
 * @return array<string,mixed>
 */
function nicheFixture(string $name, string $industry, string $url, array $data): array
{
    return [
        'version' => 'v1',
        'status' => 'ready',
        'generated_at' => now()->toIso8601String(),
        'business' => [
            'summary' => (string) ($data['summary'] ?? $name),
            'industry' => $industry,
            'positioning' => $name,
        ],
        'services' => $data['services'] ?? [],
        'vocabulary' => [
            'core_terms' => $data['terms'] ?? [],
            'forbidden_generic' => ['Field example'],
            'tone' => 'expert métier',
        ],
        'main_pages' => [
            ['path' => '/', 'title' => $name, 'role' => 'pillar'],
        ],
        'geography' => ['scope' => 'national', 'regions' => ['France']],
        'audience' => ['segments' => [['label' => 'Professionnels', 'needs' => ['expertise'], 'signals' => []]]],
        'generation_directives' => [
            'language' => 'fr',
            'locale' => 'fr',
            'site_name' => $name,
            'site_url' => $url,
            'niche' => $industry,
        ],
    ];
}

function ensureLabSite(string $siteId): SeoSite
{
    $appUrl = rtrim((string) config('app.url', 'http://127.0.0.1'), '/');

    /** @var SeoSite $site */
    $site = SeoSite::query()->firstOrCreate(
        ['site_id' => $siteId],
        [
            'name' => 'Niche Lab',
            'url' => $appUrl,
            'niche' => 'lab',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'niche-lab-token'),
            'is_active' => true,
            'settings_json' => [
                'publication' => ['mode' => 'runtime'],
            ],
        ],
    );

    return $site;
}
