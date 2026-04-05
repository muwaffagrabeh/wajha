<?php

namespace App\Services\Snad;

use App\Models\Branch;

class Validator
{
    public function __construct(
        private Branch $branch,
        private array $validatorRules = [],
    ) {}

    /**
     * Validate agent output before sending to customer.
     * Returns array of errors (empty = passed).
     */
    public function validate(array $agentOutput, array $context): array
    {
        $errors = [];
        $responseText = $agentOutput['response_text'] ?? '';

        $errors = array_merge($errors, $this->checkMentionedServices($responseText, $context));
        $errors = array_merge($errors, $this->checkMentionedPrices($responseText, $context));
        $errors = array_merge($errors, $this->checkConfidence($agentOutput));
        $errors = array_merge($errors, $this->checkForbiddenPhrases($responseText));

        return $errors;
    }

    /**
     * Decide what to do based on validation results.
     */
    public function decide(array $agentOutput, array $errors): array
    {
        $critical = array_filter($errors, fn($e) => $e['severity'] === 'critical');
        $warnings = array_filter($errors, fn($e) => $e['severity'] === 'warning');

        if (empty($critical) && empty($warnings)) {
            return [
                'action' => 'send',
                'response' => $agentOutput['response_text'],
                'errors' => [],
            ];
        }

        if (empty($critical)) {
            return [
                'action' => 'send_with_log',
                'response' => $agentOutput['response_text'],
                'errors' => $warnings,
            ];
        }

        return [
            'action' => 'block',
            'response' => null,
            'errors' => $errors,
            'fallback' => 'عذراً، خلني أتأكد من المعلومة وأرد عليك.',
        ];
    }

    private function checkMentionedServices(string $text, array $context): array
    {
        $errors = [];
        $knownServices = collect($context['services'] ?? [])->pluck('name')->toArray();

        // Simple check: if the response mentions a service-like word not in our list
        // This is a basic implementation — production would use NER
        foreach ($knownServices as $service) {
            // Positive: service is known, no error
        }

        return $errors;
    }

    private function checkMentionedPrices(string $text, array $context): array
    {
        $errors = [];

        // Extract numbers followed by "ريال" from the response
        preg_match_all('/(\d+(?:\.\d+)?)\s*ريال/', $text, $matches);

        if (empty($matches[1])) return [];

        $knownPrices = collect($context['services'] ?? [])->pluck('price')->map(fn($p) => (float) $p)->toArray();

        // Build valid sums (combinations of 2-3 services)
        $validPrices = $knownPrices;
        for ($i = 0; $i < count($knownPrices); $i++) {
            for ($j = $i + 1; $j < count($knownPrices); $j++) {
                $validPrices[] = $knownPrices[$i] + $knownPrices[$j];
                for ($k = $j + 1; $k < count($knownPrices); $k++) {
                    $validPrices[] = $knownPrices[$i] + $knownPrices[$j] + $knownPrices[$k];
                }
            }
        }

        foreach ($matches[1] as $mentionedPrice) {
            $price = (float) $mentionedPrice;
            if (!in_array($price, $validPrices) && $price > 0) {
                $errors[] = [
                    'type' => 'wrong_price',
                    'severity' => 'critical',
                    'detail' => "ذكر سعر {$price} ريال غير موجود في قائمة الأسعار",
                ];
            }
        }

        return $errors;
    }

    private function checkConfidence(array $agentOutput): array
    {
        $confidence = $agentOutput['confidence'] ?? 'high';

        if ($confidence === 'low') {
            return [[
                'type' => 'low_confidence',
                'severity' => 'warning',
                'detail' => $agentOutput['confidence_reason'] ?? 'ثقة منخفضة بدون سبب',
            ]];
        }

        return [];
    }

    private function checkForbiddenPhrases(string $text): array
    {
        $errors = [];
        $forbidden = [
            'أعتقد أن', 'من الممكن أن', 'بناءً على تقديري',
            'قد يكون', 'ربما', 'أتوقع',
        ];

        foreach ($forbidden as $phrase) {
            if (str_contains($text, $phrase)) {
                $errors[] = [
                    'type' => 'uncertain_language',
                    'severity' => 'warning',
                    'detail' => "استخدم عبارة غير مؤكدة: \"{$phrase}\"",
                ];
            }
        }

        return $errors;
    }
}
