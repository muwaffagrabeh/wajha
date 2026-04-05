<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sector_types', function (Blueprint $table) {
            $table->string('work_model')->default('team')->after('has_specialists');
        });
    }

    public function down(): void
    {
        Schema::table('sector_types', function (Blueprint $table) {
            $table->dropColumn('work_model');
        });
    }
};
