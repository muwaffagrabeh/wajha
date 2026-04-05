<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ali\AliRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestAliController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
            'session_token' => 'required|string',
            'business_id' => 'nullable|string',
        ]);

        $router = new AliRouter();
        $result = $router->route(null, $data['message'], $data['session_token'], $data['business_id'] ?? null);

        return response()->json($result);
    }
}
