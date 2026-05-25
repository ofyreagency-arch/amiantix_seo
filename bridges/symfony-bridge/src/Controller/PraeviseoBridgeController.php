<?php

declare(strict_types=1);

namespace Praeviseo\SymfonyBridge\Controller;

use Praeviseo\SymfonyBridge\Service\PraeviseoBridgeService;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class PraeviseoBridgeController extends AbstractController
{
    public function __construct(
        private readonly PraeviseoBridgeService $bridge,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            return $this->json($this->bridge->publishFromRequest($request));
        } catch (RuntimeException $exception) {
            return $this->json([
                'status' => 'error',
                'updated' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
