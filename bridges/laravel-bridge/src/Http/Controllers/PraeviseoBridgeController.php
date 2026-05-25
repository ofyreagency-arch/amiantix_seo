<?php

declare(strict_types=1);

namespace Praeviseo\LaravelBridge\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Praeviseo\LaravelBridge\Services\PraeviseoBridgeService;
use RuntimeException;

final class PraeviseoBridgeController
{
    public function __construct(
        private readonly PraeviseoBridgeService $bridge,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            return response()->json($this->bridge->publishFromRequest($request));
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'updated' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}
