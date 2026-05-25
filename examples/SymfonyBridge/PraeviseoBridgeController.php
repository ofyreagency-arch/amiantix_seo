<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PraeviseoBridgeService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class PraeviseoBridgeController extends AbstractController
{
    #[Route('/api/praeviseo/bridge/publish', name: 'praeviseo_bridge_publish', methods: ['POST'])]
    public function publish(Request $request, PraeviseoBridgeService $bridge): JsonResponse
    {
        try {
            return $this->json($bridge->publishFromRequest($request));
        } catch (RuntimeException $exception) {
            return $this->json([
                'status' => 'error',
                'updated' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
