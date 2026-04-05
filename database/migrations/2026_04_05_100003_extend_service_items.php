<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->string('service_mode')->default('at_branch')->after('deliverable');
            $table->decimal('travel_fee', 10, 2)->nullable()->after('service_mode');
            $table->integer('travel_radius_km')->nullable()->after('travel_fee');
            $table->decimal('min_travel_order', 10, 2)->nullable()->after('travel_radius_km');
        });
    }

    public function down(): void
    {
        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn(['service_mode', 'travel_fee', 'travel_radius_km', 'min_travel_order']);
        });
    }
};
