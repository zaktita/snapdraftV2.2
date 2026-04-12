<?php

namespace App\Http\Controllers;

use App\Models\WaitlistEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        WaitlistEntry::firstOrCreate(
            ['email' => strtolower(trim($request->email))],
        );

        return response()->json(['success' => true]);
    }
}
