<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PraeviseoBridgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PraeviseoBridgeController extends Controller
{
    public function __construct(
        private readonly PraeviseoBridgeService $bridge,
    ) {}

    public function publish(Request $request): JsonResponse
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
