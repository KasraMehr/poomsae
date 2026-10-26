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
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->string('tkdhub_event_id', 128)->nullable()->unique();
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('tkdhub_division_id', 128)->nullable();
            $table->unique(['tournament_id', 'tkdhub_division_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropUnique(['tournament_id', 'tkdhub_division_id']);
            $table->dropColumn('tkdhub_division_id');
        });
        Schema::table('tournaments', function (Blueprint $table): void {
            $table->dropUnique(['tkdhub_event_id']);
            $table->dropColumn('tkdhub_event_id');
        });
    }
};
