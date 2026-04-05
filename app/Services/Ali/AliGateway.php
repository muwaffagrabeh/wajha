<?php

namespace App\Services\Ali;

use App\Models\Business;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ActionLog;
use App\Models\Alert;
use Carbon\Carbon;

class AliGateway
{
    public function __construct(private Business $business) {}

    /**
     * Try to handle owner message without LLM.
     */
    public function tryHandle(string $message): ?string
    {
        $message = mb_strtolower($message);

        if ($this->isStatsQuery($message)) return $this->handleStats($message);
        if ($this->isServiceListQuery($message)) return $this->handleServiceList();
        if ($this->isAlertQuery($message)) return $this->handleAlerts();

        return null;
    }

    private function isStatsQuery(string $msg): bool
    {
        $keywords = ['كم حجز', 'كم طلب', 'كم محادثة', 'كم رسالة', 'إحصائيات', 'تقرير اليوم', 'جانا اليوم', 'أرقام اليوم'];
        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) return true;
        }
        return false;
    }

    private function isServiceListQuery(string $msg): bool
    {
        $keywords = ['قائمة الخدمات', 'قائمة المنتجات', 'وش الخدمات', 'وش المنتجات', 'عرض الخدمات'];
        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) return true;
        }
        return false;
    }

    private function isAlertQuery(string $msg): bool
    {
        $keywords = ['تنبيهات', 'أخطاء', 'مشاكل', 'فيه مشكلة'];
        foreach ($keywords as $kw) {
            if (str_contains($msg, $kw)) return true;
        }
        return false;
    }

    private function handleStats(string $msg): string
    {
        $branchIds = $this->business->branches()->pluck('id');
        $today = Carbon::today();

        $conversations = Conversation::whereIn('branch_id', $branchIds)
            ->whereDate('started_at', $today);

        $total = $conversations->count();
        $resolved = (clone $conversations)->where('status', 'resolved')->count();
        $escalated = (clone $conversations)->where('status', 'escalated')->count();

        $messages = Message::whereIn('conversation_id',
            Conversation::whereIn('branch_id', $branchIds)->whereDate('started_at', $today)->pluck('id')
        )->count();

        $blocked = Message::whereIn('conversation_id',
            Conversation::whereIn('branch_id', $branchIds)->whereDate('started_at', $today)->pluck('id')
        )->where('was_blocked', true)->count();

        $errors = Alert::where('business_id', $this->business->id)
            ->whereDate('created_at', $today)->count();

        $output = "📊 إحصائيات اليوم:\n";
        $output .= "• محادثات: {$total}\n";
        $output .= "• حُلّت: {$resolved}\n";
        $output .= "• تصعيد: {$escalated}\n";
        $output .= "• رسائل: {$messages}\n";
        $output .= "• ردود محجوبة: {$blocked}\n";
        $output .= "• تنبيهات: {$errors}\n";
        $output .= "\nشي ثاني؟";

        return $output;
    }

    private function handleServiceList(): string
    {
        $services = $this->business->serviceItems()->orderBy('category')->get();

        if ($services->isEmpty()) return "ما عندك خدمات/منتجات مضافة لحد الآن.";

        $output = "📋 قائمة الخدمات/المنتجات:\n";
        $currentCategory = '';

        foreach ($services as $s) {
            if ($s->category !== $currentCategory) {
                $currentCategory = $s->category;
                $output .= "\n{$currentCategory}:\n";
            }
            $status = $s->status === 'active' ? '✓' : '✗';
            $output .= "  {$status} {$s->name} — {$s->price} ريال\n";
        }

        $output .= "\nشي ثاني؟";
        return $output;
    }

    private function handleAlerts(): string
    {
        $alerts = Alert::where('business_id', $this->business->id)
            ->where('acknowledged', false)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        if ($alerts->isEmpty()) return "✓ ما فيه تنبيهات جديدة. الأمور تمام.";

        $output = "⚠️ آخر التنبيهات:\n";
        foreach ($alerts as $a) {
            $icon = match ($a->severity) {
                'critical' => '🔴',
                'high' => '🟠',
                'medium' => '🟡',
                default => '🔵',
            };
            $output .= "{$icon} {$a->title}: {$a->message}\n";
        }

        $output .= "\nشي ثاني؟";
        return $output;
    }
}
