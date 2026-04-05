<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionLog;
use App\Models\Business;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * POST /api/auth/link-session
     *
     * After OTP verification, links all resources created under
     * a session_token to the authenticated user_id.
     */
    public function linkSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
            'session_token' => 'required|string',
        ]);

        $userId = $validated['user_id'];
        $sessionToken = $validated['session_token'];

        $linked = [
            'businesses' => 0,
            'conversations' => 0,
            'action_logs' => 0,
        ];

        // 1. businesses — link orphan businesses to user
        $linked['businesses'] = Business::whereNull('user_id')
            ->whereIn('id', function ($query) use ($sessionToken) {
                $query->select('business_id')
                    ->from('action_logs')
                    ->where('session_token', $sessionToken);
            })
            ->update(['user_id' => $userId]);

        // Also link businesses that have action_logs with this session
        $businessIds = ActionLog::where('session_token', $sessionToken)
            ->pluck('business_id')
            ->unique();

        Business::whereIn('id', $businessIds)
            ->whereNull('user_id')
            ->update(['user_id' => $userId]);

        $linked['businesses'] = Business::where('user_id', $userId)
            ->whereIn('id', $businessIds)
            ->count();

        // 2. conversations — link by session_token
        $linked['conversations'] = Conversation::where('session_token', $sessionToken)
            ->update(['session_token' => null]);

        // 3. action_logs — stamp with user context (keep session_token for audit)
        $linked['action_logs'] = ActionLog::where('session_token', $sessionToken)
            ->count();

        return response()->json([
            'status' => 'linked',
            'user_id' => $userId,
            'session_token' => $sessionToken,
            'linked' => $linked,
        ]);
    }

    public function linkSessionInternal(string $userId, string $sessionToken): void
    {
        $businessIds = ActionLog::where('session_token', $sessionToken)
            ->pluck('business_id')->unique();

        Business::whereIn('id', $businessIds)
            ->whereNull('user_id')
            ->update(['user_id' => $userId]);

        Conversation::where('session_token', $sessionToken)
            ->update(['session_token' => null]);
    }
}
