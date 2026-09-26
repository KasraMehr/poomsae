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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
        });

        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('venue')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('timezone')->default('Asia/Tehran');
            $table->enum('status', ['draft', 'ready', 'running', 'completed', 'archived'])->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('tournament_user', function (Blueprint $table) {
            $table->foreignId('tournament_id')->constrained('tournaments')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('role', ['manager', 'operator', 'judge', 'display']);
            $table->timestamps();
            $table->primary(['tournament_id', 'user_id', 'role']);
        });

        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->unique(['tournament_id', 'name']);
        });

        Schema::create('scoring_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('version');
            $table->enum('discipline', ['recognized', 'freestyle']);
            $table->json('definition');
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['name', 'version']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->restrictOnDelete();
            $table->foreignId('scoring_rule_set_id')->nullable()->constrained('scoring_rule_sets')->restrictOnDelete();
            $table->string('name');
            $table->enum('discipline', ['recognized', 'freestyle']);
            $table->enum('entry_type', ['individual', 'pair', 'team']);
            $table->enum('gender', ['male', 'female', 'mixed', 'open']);
            $table->unsignedSmallInteger('minimum_age')->nullable();
            $table->unsignedSmallInteger('maximum_age')->nullable();
            $table->enum('format', ['round_robin', 'knockout']);
            $table->enum('execution_mode', ['alternating', 'simultaneous']);
            $table->enum('judge_count', ['5', '7']);
            $table->unsignedSmallInteger('forms_per_round')->default(2);
            $table->enum('draw_timing', ['day_start', 'before_entry', 'per_performance']);
            $table->unsignedSmallInteger('minimum_duration_seconds')->nullable();
            $table->unsignedSmallInteger('maximum_duration_seconds')->nullable();
            $table->timestamps();
            $table->unique(['tournament_id', 'name']);
        });

        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('club')->nullable();
            $table->string('federation_number')->nullable();
            $table->timestamps();
            $table->unique(['federation_number']);
            $table->index(['last_name', 'first_name']);
        });

        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('display_name');
            $table->unsignedSmallInteger('seed')->nullable();
            $table->enum('status', ['registered', 'checked_in', 'withdrawn', 'disqualified'])->default('registered');
            $table->timestamps();
            $table->unique(['category_id', 'seed']);
            $table->unique(['id', 'category_id']);
        });

        Schema::create('entry_members', function (Blueprint $table) {
            $table->foreignId('entry_id');
            $table->foreignId('category_id');
            $table->foreignId('athlete_id')->constrained('athletes')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->primary(['entry_id', 'athlete_id']);
            $table->unique(['category_id', 'athlete_id']);
            $table->unique(['entry_id', 'position']);
            $table->foreign(['entry_id', 'category_id'])->references(['id', 'category_id'])->on('entries')->restrictOnDelete();
        });

        Schema::create('competition_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sequence');
            $table->enum('status', ['pending', 'running', 'completed'])->default('pending');
            $table->timestamps();
            $table->unique(['category_id', 'sequence']);
            $table->unique(['id', 'category_id']);
        });

        Schema::create('bouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_round_id');
            $table->foreignId('category_id');
            $table->foreignId('court_id')->nullable()->constrained('courts')->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->enum('status', ['pending', 'running', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->foreign(['competition_round_id', 'category_id'])->references(['id', 'category_id'])->on('competition_rounds')->restrictOnDelete();
            $table->unique(['competition_round_id', 'sequence']);
            $table->unique(['id', 'category_id']);
        });

        Schema::create('bout_entries', function (Blueprint $table) {
            $table->foreignId('bout_id');
            $table->foreignId('entry_id');
            $table->foreignId('category_id');
            $table->enum('side', ['chung', 'hong']);
            $table->primary(['bout_id', 'entry_id']);
            $table->unique(['bout_id', 'side']);
            $table->foreign(['bout_id', 'category_id'])->references(['id', 'category_id'])->on('bouts')->restrictOnDelete();
            $table->foreign(['entry_id', 'category_id'])->references(['id', 'category_id'])->on('entries')->restrictOnDelete();
        });

        Schema::create('poomsae_forms', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
            $table->unique(['code']);
        });

        Schema::create('category_poomsae_form', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('poomsae_form_id')->constrained('poomsae_forms')->restrictOnDelete();
            $table->primary(['category_id', 'poomsae_form_id']);
        });

        Schema::create('draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('competition_round_id')->nullable()->constrained('competition_rounds')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('kind', ['bracket', 'forms']);
            $table->string('algorithm_version');
            $table->string('random_seed');
            $table->json('input_snapshot');
            $table->json('output_snapshot');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id');
            $table->foreignId('bout_id');
            $table->foreignId('entry_id');
            $table->foreignId('poomsae_form_id')->nullable()->constrained('poomsae_forms')->restrictOnDelete();
            $table->foreignId('draw_id')->nullable()->constrained('draws')->restrictOnDelete();
            $table->unsignedSmallInteger('form_number');
            $table->enum('status', ['pending', 'running', 'scoring', 'approved', 'cancelled'])->default('pending');
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('music_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['public_id']);
            $table->foreign(['bout_id', 'entry_id'])->references(['bout_id', 'entry_id'])->on('bout_entries')->restrictOnDelete();
            $table->unique(['bout_id', 'entry_id', 'form_number']);
        });

        Schema::create('judge_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bout_id')->constrained('bouts')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('seat');
            $table->timestamps();
            $table->unique(['bout_id', 'user_id']);
            $table->unique(['bout_id', 'seat']);
        });

        Schema::create('score_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained('performances')->restrictOnDelete();
            $table->foreignId('judge_assignment_id')->constrained('judge_assignments')->restrictOnDelete();
            $table->unsignedSmallInteger('revision')->default(1);
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['performance_id', 'judge_assignment_id']);
        });

        Schema::create('score_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_sheet_id')->constrained('score_sheets')->restrictOnDelete();
            $table->string('criterion');
            $table->unsignedSmallInteger('value_hundredths');
            $table->unique(['score_sheet_id', 'criterion']);
        });

        Schema::create('score_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('score_sheet_id')->constrained('score_sheets')->restrictOnDelete();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('revision');
            $table->json('snapshot');
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['score_sheet_id', 'revision']);
        });

        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained('performances')->restrictOnDelete();
            $table->foreignId('scoring_rule_set_id')->constrained('scoring_rule_sets')->restrictOnDelete();
            $table->decimal('score', 9, 6);
            $table->json('calculation_snapshot');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['performance_id']);
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_id');
            $table->string('payload_hash');
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'request_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->foreignId('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['audit_logs', 'idempotency_keys', 'results', 'score_revisions', 'score_components', 'score_sheets', 'judge_assignments', 'performances', 'draws', 'category_poomsae_form', 'poomsae_forms', 'bout_entries', 'bouts', 'competition_rounds', 'entry_members', 'entries', 'athletes', 'categories', 'scoring_rule_sets', 'courts', 'tournament_user', 'tournaments'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'is_active']);
        });
    }
};
