<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Service;

use Doctrine\ORM\EntityManagerInterface;
use Praeviseo\SymfonyBridge\Entity\PraeviseoPublishedPage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

final class PraeviseoBridgeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PraeviseoBridgeConfig $config,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function publishFromRequest(Request $request): array
    {
        $this->assertSignedRequest($request);

        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($payload)) {
            throw new RuntimeException('Payload PraeviSEO invalide.');
        }

        $siteId = trim((string) ($payload['site']['site_id'] ?? ''));
        $pageData = $payload['page'] ?? null;

        if ($siteId === '' || ! is_array($pageData) || empty($pageData['id']) || empty($pageData['slug']) || empty($pageData['title'])) {
            throw new RuntimeException('Payload PraeviSEO incomplet.');
        }

        $slug = trim((string) $pageData['slug'], '/');
        $prefix = $this->config->bridgePrefix();
        $liveUrl = $this->config->normalizedAppUrl().'/'.$prefix.'/'.$slug;

        $page = $this->entityManager
            ->getRepository(PraeviseoPublishedPage::class)
            ->findOneBy([
                'praeviseoSiteId' => $siteId,
                'externalPageId' => (int) $pageData['id'],
            ]) ?? new PraeviseoPublishedPage();

        $page->setPraeviseoSiteId($siteId);
        $page->setExternalPageId((int) $pageData['id']);
        $page->setSlug($slug);
        $page->setTitle((string) $pageData['title']);
        $page->setH1(isset($pageData['h1']) ? (string) $pageData['h1'] : null);
        $page->setMetaDescription(isset($pageData['meta_description']) ? (string) $pageData['meta_description'] : null);
        $page->setContentHtml((string) ($pageData['content'] ?? ''));
        $page->setFaqJson(is_array($pageData['faq'] ?? null) ? $pageData['faq'] : []);
        $page->setSchemaJson(is_array($pageData['schema'] ?? null) ? $pageData['schema'] : []);
        $page->setInternalLinksJson(is_array($pageData['internal_links'] ?? null) ? $pageData['internal_links'] : []);
        $page->setCanonicalUrl(! empty($pageData['canonical_url']) ? (string) $pageData['canonical_url'] : $liveUrl);
        $page->setLiveUrl(! empty($pageData['suggested_live_url']) ? (string) $pageData['suggested_live_url'] : $liveUrl);
        $page->setCluster(isset($pageData['cluster']) ? (string) $pageData['cluster'] : null);
        $page->setIsNoindex((bool) ($pageData['forced_noindex'] ?? false));
        $page->setImagePath(isset($pageData['image']['path']) ? (string) $pageData['image']['path'] : null);
        $page->setImageAlt(isset($pageData['image']['alt']) ? (string) $pageData['image']['alt'] : null);
        $page->setPublicationState('published');
        $page->setLastPublishedAt(new \DateTimeImmutable());

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return [
            'status' => 'ok',
            'updated' => true,
            'slug' => $page->getSlug(),
            'live_url' => $page->getLiveUrl() ?: $liveUrl,
        ];
    }

    private function assertSignedRequest(Request $request): void
    {
        $secret = $this->config->bridgeSecret();

        if ($secret === '') {
            throw new RuntimeException('PRAEVISEO_BRIDGE_SECRET manquant.');
        }

        $configuredSiteId = $this->config->bridgeSiteId();
        $headerSiteId = trim((string) $request->headers->get('X-Praeviseo-Site-Id', ''));
        $timestamp = trim((string) $request->headers->get('X-Praeviseo-Timestamp', ''));
        $signature = trim((string) $request->headers->get('X-Praeviseo-Signature', ''));

        if ($configuredSiteId !== '' && $headerSiteId !== $configuredSiteId) {
            throw new RuntimeException('Site PraeviSEO non autorisé.');
        }

        if ($timestamp === '' || $signature === '') {
            throw new RuntimeException('Headers de signature manquants.');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            throw new RuntimeException('Timestamp PraeviSEO expiré.');
        }

        $body = $request->getContent();
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $this->logSignatureTrace(
            timestamp: $timestamp,
            body: $body,
            receivedSignature: $signature,
            expectedSignature: $expected,
            secret: $secret,
            siteId: $headerSiteId,
        );

        if (! hash_equals($expected, $signature)) {
            throw new RuntimeException('Signature PraeviSEO invalide.');
        }
    }

    private function logSignatureTrace(
        string $timestamp,
        string $body,
        string $receivedSignature,
        string $expectedSignature,
        string $secret,
        string $siteId,
    ): void {
        try {
            $projectRoot = dirname(__DIR__, 5);
            $logDirectory = $projectRoot.'/var/log';
            $logPath = is_dir($logDirectory)
                ? $logDirectory.'/praeviseo-bridge-signature.log'
                : sys_get_temp_dir().'/praeviseo-bridge-signature.log';

            file_put_contents($logPath, json_encode([
                'logged_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                'side' => 'symfony_bridge',
                'site_id' => $siteId,
                'timestamp' => $timestamp,
                'body' => $body,
                'received_signature' => $receivedSignature,
                'expected_signature' => $expectedSignature,
                'secret' => $secret,
                'secret_sha256' => hash('sha256', $secret),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND);
        } catch (\Throwable) {
            // Never block the bridge because the debug trace could not be written.
        }
    }
}
