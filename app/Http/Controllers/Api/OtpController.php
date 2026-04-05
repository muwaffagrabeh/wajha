<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $request->validate(['phone' => 'required|string|min:10']);

        $phone = $request->phone;

        // Invalidate old codes
        OtpCode::where('phone', $phone)->whereNull('verified_at')->delete();

        $otp = OtpCode::create([
            'phone' => $phone,
            'code' => str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(5),
        ]);

        // TODO: send via SMS gateway
        // For now return code in dev mode
        return response()->json([
            'message' => 'تم إرسال رمز التحقق',
            'expires_in' => 300,
            'dev_code' => app()->isLocal() ? $otp->code : null,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:4',
            'session_token' => 'nullable|string',
        ]);

        $otp = OtpCode::where('phone', $request->phone)
            ->where('code', $request->code)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (!$otp) {
            return response()->json(['message' => 'رمز خطأ'], 422);
        }

        if ($otp->isExpired()) {
            return response()->json(['message' => 'الرمز منتهي'], 422);
        }

        $otp->update(['verified_at' => now()]);

        // Find or create user
        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            ['status' => 'active']
        );
        $user->update(['last_login_at' => now()]);

        // Link session if provided
        if ($request->session_token) {
            app(AuthController::class)->linkSessionInternal($user->id, $request->session_token);
        }

        $token = $user->createToken('dashboard')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'phone' => $user->phone,
                'name' => $user->name,
            ],
        ]);
    }
}
