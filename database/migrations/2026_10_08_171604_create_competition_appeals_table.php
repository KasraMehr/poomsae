<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('competition_appeals', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('tournament_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('performance_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('filed_by')->constrained('users')->restrictOnDelete();
            $table->string('representative_name', 150);
            $table->text('reason');
            $table->text('evidence')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('performance_version');
            $table->json('original_snapshot');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('corrections')->nullable();
            $table->json('decision_snapshot')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->json('applied_snapshot')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['tournament_id', 'status']);
            $table->index(['category_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('competition_appeals');
    }
};
