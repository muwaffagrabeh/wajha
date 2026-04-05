<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sector_types', function (Blueprint $table) {
            $table->string('blueprint')->nullable()->after('sector_rules');
            $table->json('onboarding_steps')->nullable()->after('blueprint');
            $table->json('preview')->nullable()->after('onboarding_steps');
            $table->text('agent_prompt')->nullable()->after('preview');
            $table->json('agent_rules')->nullable()->after('agent_prompt');
            $table->json('terms')->nullable()->after('agent_rules');
            $table->json('terminology')->nullable()->after('terms');
            $table->json('default_services_with_prices')->nullable()->after('terminology');
            $table->string('default_service_mode')->default('at_branch')->after('default_services_with_prices');
            $table->boolean('show_service_mode_step')->default(false)->after('default_service_mode');
            $table->boolean('has_specialists')->default(false)->after('show_service_mode_step');
            $table->json('compatibility')->nullable()->after('has_specialists');
            $table->string('default_agent_name')->nullable()->after('compatibility');
            $table->string('default_agent_gender')->default('male')->after('default_agent_name');

            // Change status to support draft/testing/approved
            // SQLite can't alter enum, so we drop and recreate
        });

        // Add approval_status separately (status enum can't be changed in SQLite)
        Schema::table('sector_types', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('sector_types', function (Blueprint $table) {
            $table->dropColumn([
                'blueprint', 'onboarding_steps', 'preview', 'agent_prompt',
                'agent_rules', 'terms', 'terminology', 'default_services_with_prices',
                'default_service_mode', 'show_service_mode_step', 'has_specialists',
                'compatibility', 'default_agent_name', 'default_agent_gender',
                'approval_status',
            ]);
        });
    }
};
