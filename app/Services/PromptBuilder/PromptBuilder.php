<?php

namespace App\Services\PromptBuilder;

use App\Actions\ActionRegistry;
use App\Blueprints\Blueprint;
use App\Models\AgentPrompt;
use App\Models\Branch;
use App\Models\Business;

class PromptBuilder
{
    public static function rebuildForBusiness(string $businessId): void
    {
        $business = Business::with(['branches', 'serviceItems', 'policies', 'sectorType'])->findOrFail($businessId);

        foreach ($business->branches as $branch) {
            self::rebuildForBranch($business, $branch);
        }
    }

    public static function rebuildForBranch(Business $business, Branch $branch): void
    {
        $sectorType = $business->sectorType;
        $bp = $sectorType ? Blueprint::fromSectorType($sectorType) : null;

        // Read rules from sector_type (DB) instead of YAML
        $agentRules = $bp->rules ?? [];
        $terms = $bp->terms ?? [];

        // Build prompt
        $identity = self::buildIdentity($business, $terms);
        $rules = self::buildRules($agentRules, $business->custom_rules);
        $escalation = self::buildEscalation($business);
        $knowledge = self::buildKnowledge($business, $branch);

        $prompt = self::assemble($identity, $rules, $escalation, $knowledge, $terms);

        // Validator rules from DB
        $validatorRules = $agentRules;

        // Gateway routes
        $gatewayRoutes = [
            'business_hours' => ['type' => 'static'],
            'price_check' => ['type' => 'db_lookup'],
            'service_list' => ['type' => 'db_lookup'],
        ];

        // Tools for snad
        $tools = array_values(ActionRegistry::forAgent('snad'));

        $currentVersion = AgentPrompt::where('branch_id', $branch->id)->max('version') ?? 0;

        AgentPrompt::create([
            'branch_id' => $branch->id,
            'prompt_text' => $prompt,
            'tools_snapshot' => $tools,
            'validator_rules' => $validatorRules,
            'gateway_routes' => $gatewayRoutes,
            'version' => $currentVersion + 1,
            'built_at' => now(),
            'built_by' => 'system',
        ]);
    }

    private static function buildIdentity(Business $business, array $terms): array
    {
        $gender = $business->agent_gender ?? 'male';
        $genderSuffix = $gender === 'female' ? 'ة' : '';

        return [
            'name' => $business->agent_name ?? "مساعد{$genderSuffix} {$business->name}",
            'role' => "وكيل{$genderSuffix} خدمة عملاء {$business->name}",
            'tone' => $business->agent_tone ?? 'ودود ومهني',
            'dialect' => $business->agent_dialect ?? 'saudi',
            'gender' => $gender,
            'language' => 'ar-SA',
        ];
    }

    private static function buildRules(array $sectorRules, ?array $customRules): array
    {
        $rules = ['critical' => [], 'high' => [], 'normal' => []];

        foreach ($sectorRules as $rule) {
            $rules['critical'][] = $rule;
        }

        $rules['critical'][] = 'لا تذكر معلومة غير موجودة في البيانات';
        $rules['critical'][] = 'لا تخترع أرقام أو أسعار';
        $rules['high'][] = 'رسالة واحدة = 3 جمل كحد أقصى إلا عند عرض قوائم';
        $rules['normal'][] = 'استخدم اسم العميل بعد معرفته';

        if ($customRules) {
            foreach ($customRules as $rule) {
                $rules['normal'][] = $rule;
            }
        }

        return $rules;
    }

    private static function buildEscalation(Business $business): array
    {
        return [
            'conditions' => ['شكوى', 'طلب سعر خاص', 'سؤال خارج النطاق', 'طلب مدير'],
            'target' => "إدارة {$business->name}",
        ];
    }

    private static function buildKnowledge(Business $business, Branch $branch): array
    {
        return [
            'services' => $business->serviceItems()->where('status', 'active')
                ->get(['id', 'name', 'price', 'type', 'category', 'duration_minutes'])->toArray(),
            'policies' => $business->policies()->pluck('value', 'key')->toArray(),
            'specialists' => $branch->specialists()->where('status', 'active')
                ->get(['id', 'name', 'role', 'service_ids'])->toArray(),
            'working_hours' => $branch->working_hours,
            'location' => ['city' => $branch->city, 'district' => $branch->district],
        ];
    }

    private static function assemble(array $identity, array $rules, array $escalation, array $knowledge, array $terms): string
    {
        $genderNote = $identity['gender'] === 'female'
            ? 'أنتِ مساعدة. خاطبي العميلات بصيغة المؤنث.'
            : 'أنت مساعد. خاطب العملاء بصيغة المذكر.';

        $customer = $terms['customer'] ?? 'عميل';
        $specialist = $terms['specialist'] ?? 'مختص';

        $xml = "<agent>\n";
        $xml .= "  <identity>\n";
        $xml .= "    <name>{$identity['name']}</name>\n";
        $xml .= "    <role>{$identity['role']}</role>\n";
        $xml .= "    <tone>{$identity['tone']}</tone>\n";
        $xml .= "    <gender_note>{$genderNote}</gender_note>\n";
        $xml .= "    <customer_term>{$customer}</customer_term>\n";
        $xml .= "    <specialist_term>{$specialist}</specialist_term>\n";
        $xml .= "  </identity>\n\n";

        $xml .= "  <rules>\n";
        foreach (['critical', 'high', 'normal'] as $priority) {
            foreach ($rules[$priority] as $rule) {
                $xml .= "    <rule priority=\"{$priority}\">{$rule}</rule>\n";
            }
        }
        $xml .= "  </rules>\n\n";

        $xml .= "  <escalation>\n";
        $xml .= "    <conditions>" . implode(' · ', $escalation['conditions']) . "</conditions>\n";
        $xml .= "    <target>{$escalation['target']}</target>\n";
        $xml .= "  </escalation>\n\n";

        $xml .= "  <knowledge>\n";
        $xml .= "    " . json_encode($knowledge, JSON_UNESCAPED_UNICODE) . "\n";
        $xml .= "  </knowledge>\n";
        $xml .= "</agent>";

        return $xml;
    }
}
