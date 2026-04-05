<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sector_schemas', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('sector_type_id');
            $table->string('attribute_key');       // e.g. "rooms"
            $table->string('label');               // "عدد الغرف"
            $table->string('label_en')->nullable();
            $table->enum('type', ['number', 'text', 'boolean', 'select', 'textarea']);
            $table->json('options')->nullable();    // ["1","2","3","4","5+"]
            $table->boolean('required')->default(false);
            $table->boolean('show_to_customer')->default(true);
            $table->boolean('filterable')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('sector_type_id')->references('id')->on('sector_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sector_schemas');
    }
};
