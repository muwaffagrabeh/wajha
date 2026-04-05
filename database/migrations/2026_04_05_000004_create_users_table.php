<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('phone')->unique();       // "05xxxxxxxx"
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->enum('locale', ['ar', 'en'])->default('ar');
            $table->string('timezone')->default('Asia/Riyadh');
            $table->string('telegram_chat_id')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
