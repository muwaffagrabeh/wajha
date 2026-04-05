<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['customer', 'agent', 'owner', 'system']);
            $table->text('content');
            $table->json('raw_output')->nullable();
            $table->string('intent')->nullable();
            $table->string('action_taken')->nullable();
            $table->enum('confidence', ['high', 'medium', 'low'])->nullable();
            $table->json('validation_result')->nullable();
            $table->boolean('was_blocked')->default(false);
            $table->text('block_reason')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('response_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
