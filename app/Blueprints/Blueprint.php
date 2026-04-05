<?php

namespace App\Blueprints;

use App\Models\SectorType;

class Blueprint
{
    public string $id;
    public array $steps;
    public array $preview;
    public array $terms;
    public array $terminology;
    public array $rules;
    public array $defaultServices;
    public string $defaultServiceMode;
    public bool $showServiceModeStep;
    public bool $hasSpecialists;
    public string $defaultAgentName;
    public string $defaultAgentGender;

    public static function fromSectorType(SectorType $type): self
    {
        $bp = new self();
        $bp->id = $type->blueprint ?? 'generic';
        $bp->steps = is_string($type->onboarding_steps) ? json_decode($type->onboarding_steps, true) : ($type->onboarding_steps ?? []);
        $bp->preview = is_string($type->preview) ? json_decode($type->preview, true) : ($type->preview ?? []);
        $bp->terms = is_string($type->terms) ? json_decode($type->terms, true) : ($type->terms ?? []);
        $bp->terminology = is_string($type->terminology) ? json_decode($type->terminology, true) : ($type->terminology ?? []);
        $bp->rules = is_string($type->agent_rules) ? json_decode($type->agent_rules, true) : ($type->agent_rules ?? []);
        $bp->defaultServices = is_string($type->default_services_with_prices) ? json_decode($type->default_services_with_prices, true) : ($type->default_services_with_prices ?? []);
        $bp->defaultServiceMode = $type->default_service_mode ?? 'at_branch';
        $bp->showServiceModeStep = (bool) $type->show_service_mode_step;
        $bp->hasSpecialists = (bool) $type->has_specialists;
        $bp->defaultAgentName = $type->default_agent_name ?? 'مساعد {business_name}';
        $bp->defaultAgentGender = $type->default_agent_gender ?? 'male';
        return $bp;
    }

    public function term(string $key, string $default = ''): string
    {
        return $this->terms[$key] ?? $default;
    }

    public function resolveAlias(string $input): string
    {
        $aliases = $this->terminology['aliases'] ?? [];
        return $aliases[$input] ?? $input;
    }

    public function findCategory(string $serviceName): ?string
    {
        $categories = $this->terminology['categories'] ?? [];
        foreach ($categories as $cat => $data) {
            if (mb_stripos($serviceName, $cat) !== false) return $cat;
            foreach ($data['variants'] ?? [] as $v) {
                if (mb_stripos($serviceName, $v) !== false) return $cat;
            }
        }
        return null;
    }
}
