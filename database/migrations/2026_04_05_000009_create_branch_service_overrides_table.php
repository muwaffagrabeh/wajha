<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_service_overrides', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('service_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_override', 10, 2)->nullable();
            $table->boolean('available')->default(true);
            $table->integer('stock_override')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'service_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_service_overrides');
    }
};
