<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Understanding\SiteProfileBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteProfileBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_excludes_technical_bridge_pages_from_site_profile(): void
    {
        $site = $this->seedSiteWithPages('amiantix-filter', 'amiante', [
            ['path' => '/', 'title' => 'Amiantix diagnostic amiante', 'h1' => 'Diagnostic amiante', 'content' => 'Repérage amiante avant travaux en copropriété.'],
            ['path' => '/ressources/slug-test', 'title' => 'Guide to Reviewing the Test Bridge Praeviseo Process', 'h1' => 'Test Bridge', 'content' => 'Field example for bridge validation.'],
        ]);

        $profile = app(SiteProfileBuilder::class)->build($site);
        $mainPaths = collect($profile['main_pages'] ?? [])->pluck('path')->all();

        $this->assertContains('/', $mainPaths);
        $this->assertNotContains('/ressources/slug-test', $mainPaths);
    }

    public function test_builds_distinct_profiles_for_amiante_and_plomberie(): void
    {
        $amiante = $this->seedSiteWithPages('amiante-site', 'amiante', [
            ['path' => '/', 'title' => 'Amiantix diagnostic amiante', 'h1' => 'Diagnostic amiante', 'content' => 'Repérage amiante avant travaux en copropriété à Paris.'],
            ['path' => '/services/diagnostic-amiante', 'title' => 'Diagnostic amiante obligatoire', 'h1' => 'Diagnostic amiante', 'content' => 'DTA et repérage SS3 pour entreprises.'],
        ]);

        $plomberie = $this->seedSiteWithPages('plomb-site', 'plomberie', [
            ['path' => '/', 'title' => 'Plomb Express dépannage', 'h1' => 'Plombier urgence', 'content' => 'Dépannage fuite et chauffe-eau à Lyon pour particuliers.'],
            ['path' => '/services/depannage', 'title' => 'Dépannage plomberie', 'h1' => 'Dépannage plomberie', 'content' => 'Intervention rapide sur canalisation et chauffe-eau.'],
        ]);

        $builder = app(SiteProfileBuilder::class);
        $amianteProfile = $builder->build($amiante);
        $plombProfile = $builder->build($plomberie);

        $this->assertSame('ready', $amianteProfile['status']);
        $this->assertSame('ready', $plombProfile['status']);
        $this->assertStringContainsString('amiante', strtolower((string) data_get($amianteProfile, 'business.summary')));
        $this->assertStringContainsString('plomb', strtolower((string) data_get($plombProfile, 'business.summary')));
        $this->assertNotEquals(
            $amianteProfile['vocabulary']['core_terms'],
            $plombProfile['vocabulary']['core_terms'],
        );
        $this->assertContains('Field example', $amianteProfile['vocabulary']['forbidden_generic']);
    }

    /**
     * @param  array<int,array<string,string>>  $pages
     */
    private function seedSiteWithPages(string $siteId, string $niche, array $pages): SeoSite
    {
        $site = SeoSite::query()->create([
            'site_id' => $siteId,
            'name' => ucfirst($niche).' Site',
            'url' => 'https://'.$siteId.'.test',
            'niche' => $niche,
            'locale' => 'fr',
            'preset' => $niche === 'amiante' ? 'amiantix' : 'generic',
            'api_token_hash' => hash('sha256', $siteId),
            'is_active' => true,
        ]);

        $crawl = SeoSiteCrawl::query()->create([
            'site_id' => $site->site_id,
            'base_url' => rtrim((string) $site->url, '/'),
            'status' => 'completed',
            'max_pages' => 20,
            'meta_json' => ['trigger' => 'test'],
        ]);

        foreach ($pages as $index => $pageData) {
            $page = SeoSitePage::query()->create([
                'site_id' => $site->site_id,
                'normalized_url' => rtrim((string) $site->url, '/').$pageData['path'],
                'url_hash' => hash('sha256', $pageData['path']),
                'path' => $pageData['path'],
                'title' => $pageData['title'],
                'meta_description' => $pageData['content'],
                'primary_h1' => $pageData['h1'],
                'indexability_state' => 'indexable',
                'authority_score' => 0.8 - ($index * 0.05),
                'pillar_likelihood' => 0.7,
                'cluster_label' => $niche,
                'latest_word_count' => 400,
            ]);

            $snapshot = SeoSitePageSnapshot::query()->create([
                'site_id' => $site->site_id,
                'site_crawl_id' => $crawl->id,
                'site_page_id' => $page->id,
                'url' => $page->normalized_url,
                'title' => $pageData['title'],
                'meta_description' => $pageData['content'],
                'h1_json' => [$pageData['h1']],
                'h2_json' => ['Service', 'Méthode'],
                'content_text' => $pageData['content'],
                'word_count' => 400,
                'is_indexable' => true,
                'status_code' => 200,
                'observed_at' => now(),
            ]);

            $page->forceFill(['last_snapshot_id' => $snapshot->id])->save();
        }

        return $site->fresh();
    }
}
