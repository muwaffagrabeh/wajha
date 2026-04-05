<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ali\AliRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AliChatController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string',
            'business_id' => 'nullable|string',
        ]);

        $user = $request->user();
        $sessionToken = $user->id; // use user_id as session for authenticated

        $router = new AliRouter();
        $result = $router->route($user, $data['message'], $sessionToken, $data['business_id'] ?? null);

        return response()->json($result);
    }
}
