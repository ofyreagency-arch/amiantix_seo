<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PraeviseoPublishedPage;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;

final class PraeviseoBridgeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function publishFromRequest(Request $request): array
    {
        $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $pageData = $payload['page'] ?? null;

        if (! is_array($pageData) || empty($pageData['slug'])) {
            throw new RuntimeException('Payload page invalide.');
        }

        $page = $this->entityManager
            ->getRepository(PraeviseoPublishedPage::class)
            ->findOneBy(['slug' => (string) $pageData['slug']]) ?? new PraeviseoPublishedPage();

        $page->setSlug((string) $pageData['slug']);
        $page->setTitle((string) ($pageData['title'] ?? ''));
        $page->setMetaDescription((string) ($pageData['meta_description'] ?? ''));
        $page->setContentHtml((string) ($pageData['content_html'] ?? ''));
        $page->setFaqJson($pageData['faq'] ?? []);
        $page->setSchemaJson($pageData['schema'] ?? []);
        $page->setCanonicalUrl((string) ($pageData['canonical_url'] ?? ''));
        $page->setNoindex((bool) ($pageData['noindex'] ?? false));

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        $prefix = trim((string) ($_ENV['PRAEVISEO_BRIDGE_PREFIX'] ?? 'ressources'), '/');
        $baseUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');

        return [
            'status' => 'ok',
            'updated' => true,
            'slug' => $page->getSlug(),
            'live_url' => $baseUrl.'/'.$prefix.'/'.$page->getSlug(),
        ];
    }
}
