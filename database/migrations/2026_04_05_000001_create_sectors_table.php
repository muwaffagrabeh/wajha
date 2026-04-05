<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table) {
            $table->string('id')->primary(); // e.g. "health_beauty"
            $table->string('label');         // "صحة وتجميل"
            $table->string('label_en');      // "Health & Beauty"
            $table->string('icon');          // "💇‍♀️"
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};
