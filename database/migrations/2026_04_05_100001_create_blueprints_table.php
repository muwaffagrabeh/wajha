<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blueprints', function (Blueprint $table) {
            $table->string('id')->primary();          // "appointments"
            $table->string('label');                   // "حجز مواعيد مع مختصين"
            $table->string('label_en');                // "Appointments & Specialists"
            $table->json('base_flow');                 // abstract steps
            $table->json('base_features');             // ["حجز", "تذكير", "تقييم"]
            $table->json('requires');                  // ["services", "specialists"]
            $table->json('optional')->nullable();      // ["service_mode"]
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
