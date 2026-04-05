<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. businesses.user_id → nullable
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('user_id', 26)->nullable()->change();
        });

        // 2. session_token on conversations
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('session_token')->nullable()->after('customer_id');
        });

        // 3. session_token on action_logs
        Schema::table('action_logs', function (Blueprint $table) {
            $table->string('session_token')->nullable()->after('branch_id');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('user_id', 26)->nullable(false)->change();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('session_token');
        });

        Schema::table('action_logs', function (Blueprint $table) {
            $table->dropColumn('session_token');
        });
    }
};
