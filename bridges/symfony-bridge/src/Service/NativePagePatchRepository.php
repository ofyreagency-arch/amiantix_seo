<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Service;

use Doctrine\ORM\EntityManagerInterface;
use Praeviseo\SymfonyBridge\Entity\PraeviseoNativePagePatch;

final class NativePagePatchRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PraeviseoBridgeConfig $config,
        private readonly NativePageHtmlPatcher $patcher,
    ) {}

    /**
     * @return array{title:string,meta_description:?string,content_html:string,faq_json:array<int,mixed>}|null
     */
    public function findPublishedPatchForPath(string $path): ?array
    {
        $siteId = $this->config->bridgeSiteId();

        if ($siteId === '') {
            return null;
        }

        $normalizedPath = $this->patcher->normalizePath($path);

        $patch = $this->entityManager
            ->getRepository(PraeviseoNativePagePatch::class)
            ->findOneBy([
                'praeviseoSiteId' => $siteId,
                'targetPath' => $normalizedPath,
                'publicationState' => 'published',
            ]);

        if (! $patch instanceof PraeviseoNativePagePatch) {
            return null;
        }

        return [
            'title' => $patch->getTitle(),
            'meta_description' => $patch->getMetaDescription(),
            'content_html' => $patch->getContentHtml(),
            'faq_json' => $patch->getFaqJson(),
        ];
    }
}
