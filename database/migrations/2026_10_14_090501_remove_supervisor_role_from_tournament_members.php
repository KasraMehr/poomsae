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
        $supervisors = DB::table('tournament_user')->where('role', 'supervisor')->get();
        foreach ($supervisors as $supervisor) {
            DB::table('tournament_user')
                ->where('tournament_id', $supervisor->tournament_id)
                ->where('user_id', $supervisor->user_id)
                ->where('role', 'supervisor')
                ->delete();

            DB::table('tournament_user')->insertOrIgnore([
                'tournament_id' => $supervisor->tournament_id,
                'user_id' => $supervisor->user_id,
                'role' => 'operator',
                'created_at' => $supervisor->created_at ?? now(),
                'updated_at' => $supervisor->updated_at ?? now(),
            ]);
        }

        Schema::table('tournament_user', function (Blueprint $table): void {
            $table->enum('role', ['manager', 'operator', 'judge', 'display'])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_user', function (Blueprint $table): void {
            $table->enum('role', ['manager', 'operator', 'judge', 'display', 'supervisor'])->change();
        });
    }
};
