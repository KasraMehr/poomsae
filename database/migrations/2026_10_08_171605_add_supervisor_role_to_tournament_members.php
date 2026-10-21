<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tournament_user', function (Blueprint $table): void {
            $table->enum('role', ['manager', 'operator', 'judge', 'display', 'supervisor'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tournament_user')->where('role', 'supervisor')->exists()) {
            throw new RuntimeException('Remove supervisor assignments explicitly before rolling back this migration.');
        }

        Schema::table('tournament_user', function (Blueprint $table): void {
            $table->enum('role', ['manager', 'operator', 'judge', 'display'])->change();
        });
    }
};
