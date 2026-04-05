<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('business_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['error_caught', 'escalation', 'suggestion', 'drift_detected', 'low_confidence']);
            $table->enum('severity', ['critical', 'high', 'medium', 'low']);
            $table->string('title');
            $table->text('message');
            $table->foreignUlid('related_conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
