<?php

namespace App\Services\Ali;

class IntentClassifier
{
    /**
     * Classify owner message intent from LLM structured output.
     */
    public static function classifyFromLLM(array $parsed): array
    {
        return [
            'intent' => $parsed['intent'] ?? 'unknown',
            'entities' => $parsed['entities'] ?? [],
            'action' => $parsed['action'] ?? null,
            'params' => $parsed['params'] ?? [],
            'needs_confirmation' => $parsed['needs_confirmation'] ?? false,
            'question' => $parsed['question'] ?? null,
        ];
    }
}
