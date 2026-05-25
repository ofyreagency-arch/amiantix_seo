<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Praeviseo\SymfonyBridge\Entity\PraeviseoPublishedPage;
use Praeviseo\SymfonyBridge\Service\PraeviseoBridgeConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

final class PraeviseoPublishedPageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PraeviseoBridgeConfig $config,
    ) {}

    public function __invoke(string $prefix, string $slug): Response
    {
        if ($prefix !== $this->config->bridgePrefix()) {
            throw $this->createNotFoundException();
        }

        $page = $this->entityManager
            ->getRepository(PraeviseoPublishedPage::class)
            ->findOneBy(['slug' => $slug]);

        if (! $page instanceof PraeviseoPublishedPage) {
            throw $this->createNotFoundException();
        }

        return $this->render('@PraeviseoSymfonyBridge/published_page.html.twig', [
            'page' => $page,
        ]);
    }
}
