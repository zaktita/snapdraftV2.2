<?php

namespace App\Http\Controllers;

use App\Services\MediaAccess;
use App\Services\UserMediaStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public function show(Request $request, string $path, UserMediaStorage $storage, MediaAccess $access): StreamedResponse
    {
        $path = ltrim($path, '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        if (! $access->userCanAccessPath($request->user(), $path)) {
            abort(403);
        }

        if (! $storage->exists($path)) {
            abort(404);
        }

        $mime = $storage->mimeType($path);

        return response()->stream(function () use ($storage, $path) {
            echo $storage->get($path);
        }, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
