<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('sector_type_id');
            $table->string('name');                    // "صالون لمسة"
            $table->string('name_en')->nullable();     // "Lamsa Salon"
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->string('default_currency')->default('SAR');
            $table->json('active_patterns');            // copied from sector_types.default_patterns
            $table->json('active_layers');
            $table->json('custom_rules')->nullable();
            $table->string('agent_name')->nullable();   // "مساعدة صالون لمسة"
            $table->string('agent_tone')->default('ودود ومهني');
            $table->string('agent_dialect')->default('saudi');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('sector_type_id')->references('id')->on('sector_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
