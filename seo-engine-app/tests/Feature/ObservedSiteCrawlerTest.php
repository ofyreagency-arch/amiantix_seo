<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SeoSite;
use App\Models\SeoSiteCrawl;
use App\Models\SeoSiteCrawlIssue;
use App\Models\SeoSiteLink;
use App\Models\SeoSitePage;
use App\Models\SeoSitePageSnapshot;
use App\Models\SeoSiteSchema;
use App\Models\SeoSiteSitemap;
use App\ObservedSite\SiteCrawlerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObservedSiteCrawlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_observed_site_layer_from_robots_sitemap_and_pages(): void
    {
        $site = SeoSite::query()->create([
            'site_id' => 'crawl-site',
            'name' => 'Crawl Site',
            'url' => 'https://crawl.test',
            'niche' => 'general',
            'locale' => 'fr',
            'preset' => 'generic',
            'api_token_hash' => hash('sha256', 'token'),
            'is_active' => true,
        ]);

        Http::fake([
            'https://crawl.test/robots.txt' => Http::response("User-agent: *\nSitemap: https://crawl.test/sitemap.xml\n", 200),
            'https://crawl.test/sitemap.xml' => Http::response(
                <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://crawl.test/</loc></url>
  <url><loc>https://crawl.test/diagnostic-amiante</loc></url>
  <url><loc>https://crawl.test/contact</loc></url>
</urlset>
XML,
                200,
                ['Content-Type' => 'application/xml']
            ),
            'https://crawl.test' => Http::response(
                <<<'HTML'
<html>
  <head>
    <title>Accueil SEO</title>
    <meta name="description" content="Accueil du site" />
    <link rel="canonical" href="https://crawl.test/" />
    <script type="application/ld+json">{"@type":"WebSite","name":"Crawl Test"}</script>
  </head>
  <body>
    <h1>Accueil</h1>
    <h2>Diagnostic amiante</h2>
    <a href="/diagnostic-amiante">Diagnostic amiante</a>
    <a href="/contact">Contact</a>
    <a href="https://external.test/resource">Ressource externe</a>
  </body>
</html>
HTML,
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
            'https://crawl.test/' => Http::response(
                <<<'HTML'
<html>
  <head>
    <title>Accueil SEO</title>
    <meta name="description" content="Accueil du site" />
    <link rel="canonical" href="https://crawl.test/" />
    <script type="application/ld+json">{"@type":"WebSite","name":"Crawl Test"}</script>
  </head>
  <body>
    <h1>Accueil</h1>
    <h2>Diagnostic amiante</h2>
    <a href="/diagnostic-amiante">Diagnostic amiante</a>
    <a href="/contact">Contact</a>
    <a href="https://external.test/resource">Ressource externe</a>
  </body>
</html>
HTML,
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
            'https://crawl.test/diagnostic-amiante' => Http::response(
                <<<'HTML'
<html>
  <head>
    <title>Diagnostic amiante</title>
    <meta name="description" content="Page diagnostic" />
    <link rel="canonical" href="https://crawl.test/diagnostic-amiante" />
    <script type="application/ld+json">{"@type":"Service","name":"Diagnostic amiante"}</script>
  </head>
  <body>
    <h1>Diagnostic amiante</h1>
    <h2>Quand réaliser un diagnostic</h2>
    <h3>Avant travaux</h3>
    <p>Diagnostic amiante avant travaux et avant vente avec obligations.</p>
    <a href="/">Accueil</a>
  </body>
</html>
HTML,
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
            'https://crawl.test/contact' => Http::response(
                <<<'HTML'
<html>
  <head>
    <title>Contact</title>
    <meta name="robots" content="noindex,follow" />
  </head>
  <body>
    <h1>Contact</h1>
    <p>Contactez-nous.</p>
  </body>
</html>
HTML,
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            ),
        ]);

        /** @var SiteCrawlerService $crawler */
        $crawler = app(SiteCrawlerService::class);
        $results = $crawler->crawl($site, 10);

        $this->assertNotEmpty($results);
        $this->assertDatabaseCount('seo_site_crawls', 1);
        $this->assertDatabaseHas('seo_site_crawls', [
            'site_id' => $site->site_id,
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('seo_site_pages', [
            'site_id' => $site->site_id,
            'path' => '/',
        ]);
        $this->assertDatabaseHas('seo_site_pages', [
            'site_id' => $site->site_id,
            'path' => '/diagnostic-amiante',
        ]);
        $this->assertDatabaseHas('seo_site_pages', [
            'site_id' => $site->site_id,
            'path' => '/contact',
        ]);
        $this->assertSame(3, SeoSitePageSnapshot::query()->where('site_id', $site->site_id)->count());
        $this->assertSame(1, SeoSiteSitemap::query()->where('site_id', $site->site_id)->count());
        $this->assertGreaterThanOrEqual(2, SeoSiteLink::query()->where('site_id', $site->site_id)->count());
        $this->assertSame(2, SeoSiteSchema::query()->where('site_id', $site->site_id)->count());

        $contactPage = SeoSitePage::query()
            ->where('site_id', $site->site_id)
            ->where('path', '/contact')
            ->firstOrFail();

        $this->assertSame('noindex', $contactPage->indexability_state);
        $this->assertGreaterThanOrEqual(1, SeoSiteCrawlIssue::query()
            ->where('site_id', $site->site_id)
            ->where('issue_type', 'noindex_detected')
            ->count());

        $crawl = SeoSiteCrawl::query()->where('site_id', $site->site_id)->firstOrFail();
        $this->assertSame(3, $crawl->crawled_url_count);
    }
}
