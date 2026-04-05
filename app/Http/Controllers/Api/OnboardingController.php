<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Onboarding\OnboardingFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function message(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_token' => 'required|string',
            'message' => 'required|string',
        ]);

        $flow = new OnboardingFlow();
        $result = $flow->handle($data['session_token'], $data['message']);

        return response()->json($result);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_token' => 'required|string',
        ]);

        cache()->forget("onboarding:{$data['session_token']}");

        return response()->json(['status' => 'reset']);
    }

    public function session(string $token): JsonResponse
    {
        $data = cache()->get("onboarding:{$token}", []);

        return response()->json($data);
    }
}
