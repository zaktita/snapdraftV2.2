<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FalProxyController extends Controller
{
    private const FAL_QUEUE_BASE = 'https://queue.fal.run';
    private const UPSCALE_MODEL  = 'fal-ai/seedvr/upscale/image';

    /**
     * Submit an AI upscale job to FAL.ai via a server-side proxy.
     * The FAL API key never leaves the server.
     */
    public function submitUpscale(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image_url'      => 'required|string|max:14000000',
            'upscale_factor' => 'required|integer|in:2,4',
        ]);

        $apiKey = config('services.fal.api_key');
        if (empty($apiKey)) {
            Log::error('[fal-proxy] FAL_API_KEY not configured');
            return response()->json(['message' => 'Upscale service not available'], 503);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->post(self::FAL_QUEUE_BASE . '/' . self::UPSCALE_MODEL, [
            'input' => [
                'image_url'      => $validated['image_url'],
                'upscale_factor' => $validated['upscale_factor'],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('[fal-proxy] Submit failed', [
                'status' => $response->status(),
                'user_id' => $request->user()?->id,
            ]);
            return response()->json(['message' => 'Upscale request failed'], 500);
        }

        $user = $request->user();
        if ($user && $user->hasActiveSubscription()) {
            $creditCost = (int) $validated['upscale_factor'];
            $user->useCredit($creditCost);
        }

        Log::info('[fal-proxy] Upscale submitted', [
            'user_id'    => $request->user()?->id,
            'factor'     => $validated['upscale_factor'],
            'request_id' => $response->json('request_id'),
        ]);

        return response()->json($response->json());
    }

    /**
     * Check the status of a FAL.ai upscale job.
     * request_id is strictly validated to prevent SSRF.
     */
    public function upscaleStatus(Request $request, string $requestId): JsonResponse
    {
        if (!preg_match('/^[a-zA-Z0-9_\-]{8,128}$/', $requestId)) {
            return response()->json(['message' => 'Invalid request ID'], 422);
        }

        $apiKey = config('services.fal.api_key');
        if (empty($apiKey)) {
            return response()->json(['message' => 'Upscale service not available'], 503);
        }

        $url = self::FAL_QUEUE_BASE . '/' . self::UPSCALE_MODEL
            . '/requests/' . $requestId . '/status';

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $apiKey,
        ])->get($url);

        if (!$response->successful()) {
            Log::error('[fal-proxy] Status check failed', [
                'request_id' => $requestId,
                'status'     => $response->status(),
            ]);
            return response()->json(['message' => 'Status check failed'], 500);
        }

        return response()->json($response->json());
    }
}
