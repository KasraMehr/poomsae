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
            $table->unsignedSmallInteger('minimum_rest_seconds')->default(0);
            $table->unsignedSmallInteger('time_penalty_hundredths')->default(30);
        });
        Schema::table('performances', function (Blueprint $table): void {
            $table->json('timing_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performances', function (Blueprint $table): void {
            $table->dropColumn('timing_snapshot');
        });
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['minimum_rest_seconds', 'time_penalty_hundredths']);
        });
    }
};
