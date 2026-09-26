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
        Schema::table('score_sheets', function (Blueprint $table) {
            $table->foreignId('submitted_by')->nullable()->after('judge_assignment_id')->constrained('users')->nullOnDelete();
            $table->string('submission_mode', 24)->default('judge')->after('submitted_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('score_sheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn('submission_mode');
        });
    }
};
