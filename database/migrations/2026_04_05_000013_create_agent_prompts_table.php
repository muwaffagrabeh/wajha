<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_prompts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->longText('prompt_text');
            $table->json('tools_snapshot')->nullable();
            $table->json('validator_rules')->nullable();
            $table->json('gateway_routes')->nullable();
            $table->integer('version')->default(1);
            $table->timestamp('built_at');
            $table->enum('built_by', ['ali', 'system', 'manual'])->default('system');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_prompts');
    }
};
