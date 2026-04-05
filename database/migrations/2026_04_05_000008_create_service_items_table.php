<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');                      // "قص شعر"
            $table->string('name_en')->nullable();
            $table->enum('type', ['service', 'product']);
            $table->string('category')->nullable();      // "شعر"
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);             // 50.00
            $table->enum('price_model', ['fixed', 'starting_from', 'per_unit', 'quote'])->default('fixed');
            $table->string('price_unit')->nullable();    // "شهري" | "للمتر"
            $table->string('currency')->default('SAR');
            $table->integer('duration_minutes')->nullable(); // 30
            $table->boolean('requires_booking')->default(false);
            $table->boolean('requires_specialist')->default(false);
            $table->enum('deliverable', ['in_person', 'delivery', 'digital'])->default('in_person');
            $table->integer('stock_quantity')->nullable();
            $table->json('media')->nullable();
            $table->json('attributes')->nullable();      // per sector_schemas
            $table->json('tags')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
