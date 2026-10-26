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
        Schema::table('categories', function (Blueprint $table): void {
            $table->unsignedSmallInteger('planned_round_count')->nullable();
            $table->boolean('allow_form_repetition')->default(true);
            $table->string('form_draw_timing')->default('morning');
            $table->string('form_draw_time', 5)->default('08:00');
            $table->string('management_source')->nullable();
            $table->unsignedInteger('management_version')->default(0);
            $table->string('management_hash', 64)->nullable();
            $table->json('management_snapshot')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
        Schema::table('competition_rounds', function (Blueprint $table): void {
            $table->json('form_sequence')->nullable();
            $table->timestamp('forms_drawn_at')->nullable();
            $table->foreignId('form_draw_id')->nullable()->constrained('draws')->restrictOnDelete();
            $table->unsignedInteger('schedule_version')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->unsignedInteger('source_version')->default(0);
            $table->json('source_snapshot')->nullable();
            $table->string('source_hash', 64)->nullable();
        });
        Schema::table('entries', function (Blueprint $table): void {
            $table->string('external_id')->nullable();
            $table->unique(['category_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->dropUnique(['category_id', 'external_id']);
            $table->dropColumn('external_id');
        });
        Schema::table('competition_rounds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('form_draw_id');
            $table->dropColumn(['form_sequence', 'forms_drawn_at', 'schedule_version', 'scheduled_at', 'started_at', 'source_version', 'source_snapshot', 'source_hash']);
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['planned_round_count', 'allow_form_repetition', 'form_draw_timing', 'form_draw_time', 'management_source', 'management_version', 'management_hash', 'management_snapshot', 'synced_at']);
        });
    }
};
