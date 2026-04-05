<?php

namespace App\Services\Snad;

use App\Models\Branch;
use App\Models\ServiceItem;

class Gateway
{
    public function __construct(
        private Branch $branch,
        private array $routes = [],
    ) {}

    /**
     * Try to handle the message without calling the LLM.
     * Returns a response string if handled, null if it needs the LLM.
     */
    public function tryHandle(string $message): ?string
    {
        $intent = $this->detectIntent($message);

        return match ($intent) {
            'business_hours' => $this->handleBusinessHours(),
            'price_check' => $this->handlePriceCheck($message),
            'service_list' => $this->handleServiceList(),
            'location' => $this->handleLocation(),
            default => null, // needs LLM
        };
    }

    /**
     * Enrich context before sending to LLM.
     */
    public function enrichContext(?string $customerId = null): array
    {
        $business = $this->branch->business;

        return [
            'services' => $business->serviceItems()
                ->where('status', 'active')
                ->get(['id', 'name', 'price', 'type', 'category', 'duration_minutes'])
                ->toArray(),
            'specialists' => $this->branch->specialists()
                ->where('status', 'active')
                ->get(['id', 'name', 'role', 'service_ids'])
                ->toArray(),
            'working_hours' => $this->branch->working_hours,
            'policies' => $business->policies()->pluck('value', 'key')->toArray(),
            'location' => [
                'city' => $this->branch->city,
                'district' => $this->branch->district,
            ],
            'current_time' => now()->timezone('Asia/Riyadh')->format('Y-m-d H:i'),
        ];
    }

    private function detectIntent(string $message): ?string
    {
        $message = mb_strtolower($message);

        $hourKeywords = ['أوقات', 'ساعات', 'متى تفتحون', 'متى تقفلون', 'دوام', 'وقت العمل'];
        foreach ($hourKeywords as $kw) {
            if (str_contains($message, $kw)) return 'business_hours';
        }

        $priceKeywords = ['كم سعر', 'بكم', 'أسعار', 'سعر'];
        foreach ($priceKeywords as $kw) {
            if (str_contains($message, $kw)) return 'price_check';
        }

        $serviceKeywords = ['وش عندكم', 'الخدمات', 'قائمة', 'منيو', 'خدماتكم'];
        foreach ($serviceKeywords as $kw) {
            if (str_contains($message, $kw)) return 'service_list';
        }

        $locationKeywords = ['وين', 'العنوان', 'الموقع', 'كيف أوصلكم'];
        foreach ($locationKeywords as $kw) {
            if (str_contains($message, $kw)) return 'location';
        }

        return null;
    }

    private function handleBusinessHours(): string
    {
        $hours = $this->branch->working_hours;
        if (!$hours) return 'للأسف ما عندي معلومات عن أوقات العمل حالياً.';

        $from = $hours['from'] ?? '?';
        $to = $hours['to'] ?? '?';
        return "أوقات العمل من {$from} إلى {$to}.";
    }

    private function handlePriceCheck(string $message): ?string
    {
        $services = $this->branch->business->serviceItems()->where('status', 'active')->get();

        foreach ($services as $service) {
            if (str_contains($message, $service->name)) {
                return "{$service->name}: {$service->price} ريال.";
            }
        }

        // If asking about prices generally, list all
        if (str_contains($message, 'أسعار')) {
            $list = $services->map(fn($s) => "• {$s->name}: {$s->price} ريال")->join("\n");
            return "أسعارنا:\n{$list}";
        }

        return null; // couldn't match a specific service
    }

    private function handleServiceList(): string
    {
        $services = $this->branch->business->serviceItems()
            ->where('status', 'active')
            ->get();

        $list = $services->map(fn($s) => "• {$s->name} — {$s->price} ريال")->join("\n");
        return "خدماتنا:\n{$list}";
    }

    private function handleLocation(): string
    {
        $parts = array_filter([
            $this->branch->district,
            $this->branch->city,
            $this->branch->address,
        ]);

        if (empty($parts)) return 'للأسف ما عندي تفاصيل الموقع حالياً.';

        return 'موقعنا: ' . implode('، ', $parts) . '.';
    }
}
