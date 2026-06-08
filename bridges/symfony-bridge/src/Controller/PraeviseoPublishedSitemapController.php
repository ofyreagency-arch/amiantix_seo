<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Praeviseo\SymfonyBridge\Entity\PraeviseoPublishedPage;
use Praeviseo\SymfonyBridge\Service\PraeviseoBridgeConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class PraeviseoPublishedSitemapController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PraeviseoBridgeConfig $config,
    ) {}

    public function __invoke(string $sitemapPath): Response
    {
        if ($sitemapPath !== $this->config->bridgePrefix().'-sitemap.xml') {
            throw $this->createNotFoundException();
        }

        $pages = $this->entityManager
            ->getRepository(PraeviseoPublishedPage::class)
            ->findBy(
                ['publicationState' => 'published'],
                ['lastPublishedAt' => 'DESC'],
            );

        $response = $this->render('@PraeviseoSymfonyBridge/published_sitemap.xml.twig', [
            'pages' => $pages,
        ]);

        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response;
    }
}
