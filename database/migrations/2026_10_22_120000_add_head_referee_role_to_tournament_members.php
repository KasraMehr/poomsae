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
        Schema::table('tournament_user', function (Blueprint $table): void {
            $table->enum('role', ['manager', 'operator', 'judge', 'head_referee'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_user', function (Blueprint $table): void {
            $table->enum('role', ['manager', 'operator', 'judge'])->change();
        });
    }
};
