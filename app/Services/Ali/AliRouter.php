<?php

namespace App\Services\Ali;

use App\Models\Business;
use App\Models\User;
use App\Services\Onboarding\OnboardingFlow;

class AliRouter
{
    public function route(?User $user, string $message, string $sessionToken, ?string $businessId = null): array
    {
        // ── Super Admin ──
        if ($user && $user->role === 'super_admin') {
            return $this->handleSuperAdmin($user, $message, $sessionToken, $businessId);
        }

        // ── Subscriber with existing business ──
        $business = null;
        if ($businessId) {
            $business = Business::find($businessId);
        } elseif ($user) {
            $business = Business::where('user_id', $user->id)->first();
        }

        if ($business) {
            // Existing business → normal Ali (manage mode)
            $ali = new AliAgent($user, $business, $sessionToken);
            return $ali->handleMessage($message);
        }

        // ── No business yet → Onboarding ──
        $flow = new OnboardingFlow();
        $result = $flow->handle($sessionToken, $message, $user);

        // If DONE and delegate, switch to manage mode
        if (($result['delegate'] ?? false) && !empty($result['business_id'])) {
            $business = Business::find($result['business_id']);
            if ($business) {
                $ali = new AliAgent($user, $business, $sessionToken);
                return $ali->handleMessage($message);
            }
        }

        return $result;
    }

    private function handleSuperAdmin(User $user, string $message, string $sessionToken, ?string $businessId): array
    {
        // For now, super admin uses same flow but can see draft/testing sector types
        $business = $businessId ? Business::find($businessId) : Business::where('user_id', $user->id)->first();

        if ($business) {
            $ali = new AliAgent($user, $business, $sessionToken);
            return $ali->handleMessage($message);
        }

        $flow = new OnboardingFlow();
        return $flow->handle($sessionToken, $message, $user);
    }
}
