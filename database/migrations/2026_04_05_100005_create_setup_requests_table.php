<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setup_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('session_token')->nullable();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('requested_type');                // "مغسلة سيارات"
            $table->string('matched_blueprint')->nullable(); // "appointments"
            $table->enum('match_level', ['full', 'partial', 'none'])->default('none');
            $table->json('collected_data')->nullable();
            $table->enum('status', ['pending', 'in_review', 'ready', 'rejected'])->default('pending');
            $table->text('reviewer_notes')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setup_requests');
    }
};
