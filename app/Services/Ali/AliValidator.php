<?php

namespace App\Services\Ali;

use App\Models\Business;

class AliValidator
{
    public function __construct(private Business $business) {}

    /**
     * Validate action before execution.
     */
    public function validateAction(string $action, array $params): array
    {
        $errors = [];

        $errors = match ($action) {
            'create_service' => $this->validateCreateService($params),
            'update_service' => $this->validateUpdateService($params),
            'toggle_service' => $this->validateToggleService($params),
            'update_working_hours' => $this->validateWorkingHours($params),
            default => [],
        };

        return $errors;
    }

    private function validateCreateService(array $params): array
    {
        $errors = [];

        // Check duplicate name
        $name = $params['name'] ?? '';
        if ($name) {
            $existing = $this->business->serviceItems()
                ->where('name', $name)
                ->first();

            if ($existing) {
                $errors[] = [
                    'type' => 'duplicate_service',
                    'severity' => 'critical',
                    'detail' => "الخدمة \"{$name}\" موجودة مسبقاً بسعر {$existing->price} ريال. تبي تحدّث السعر بدل ما تضيفها؟",
                ];
            }
        }

        // Check reasonable price
        $price = $params['price'] ?? 0;
        if ($price <= 0) {
            $errors[] = [
                'type' => 'invalid_price',
                'severity' => 'critical',
                'detail' => 'السعر لازم يكون أكبر من صفر.',
            ];
        }

        if ($price > 50000) {
            $errors[] = [
                'type' => 'suspicious_price',
                'severity' => 'warning',
                'detail' => "السعر {$price} ريال يبدو مرتفع. متأكد؟",
            ];
        }

        // Check missing name
        if (empty($name)) {
            $errors[] = [
                'type' => 'missing_name',
                'severity' => 'critical',
                'detail' => 'وش اسم الخدمة/المنتج؟',
            ];
        }

        return $errors;
    }

    private function validateUpdateService(array $params): array
    {
        $errors = [];

        $price = $params['price'] ?? null;
        if ($price !== null && $price <= 0) {
            $errors[] = [
                'type' => 'invalid_price',
                'severity' => 'critical',
                'detail' => 'السعر لازم يكون أكبر من صفر.',
            ];
        }

        return $errors;
    }

    private function validateToggleService(array $params): array
    {
        return [];
    }

    private function validateWorkingHours(array $params): array
    {
        $errors = [];
        $hours = $params['working_hours'] ?? [];

        if (empty($hours['from']) || empty($hours['to'])) {
            $errors[] = [
                'type' => 'incomplete_hours',
                'severity' => 'critical',
                'detail' => 'أحتاج وقت البداية والنهاية.',
            ];
        }

        return $errors;
    }
}
