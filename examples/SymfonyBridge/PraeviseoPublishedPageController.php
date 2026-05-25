<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PraeviseoPublishedPage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PraeviseoPublishedPageController extends AbstractController
{
    #[Route('/{prefix}/{slug}', name: 'praeviseo_published_page', methods: ['GET'], requirements: ['prefix' => '.+'])]
    public function show(string $prefix, string $slug, ManagerRegistry $doctrine): Response
    {
        if ($prefix !== trim((string) ($_ENV['PRAEVISEO_BRIDGE_PREFIX'] ?? 'ressources'), '/')) {
            throw $this->createNotFoundException();
        }

        $page = $doctrine->getRepository(PraeviseoPublishedPage::class)->findOneBy(['slug' => $slug]);

        if (! $page instanceof PraeviseoPublishedPage) {
            throw $this->createNotFoundException();
        }

        return $this->render('praeviseo/published_page.html.twig', [
            'page' => $page,
        ]);
    }
}
