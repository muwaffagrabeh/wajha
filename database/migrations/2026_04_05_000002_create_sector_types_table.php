<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_types', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. "salon_women"
            $table->string('sector_id');
            $table->string('label');          // "صالون نسائي"
            $table->string('label_en');       // "Women's Salon"
            $table->json('default_patterns'); // ["booking","catalog_browse"]
            $table->json('default_layers');   // ["reminder","rating"]
            $table->string('sector_rules')->nullable(); // e.g. "medical"
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sector_id')->references('id')->on('sectors')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_types');
    }
};
