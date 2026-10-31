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
        $displays = DB::table('tournament_user')->where('role', 'display')->get();
        foreach ($displays as $display) {
            DB::table('tournament_user')
                ->where('tournament_id', $display->tournament_id)
                ->where('user_id', $display->user_id)
                ->where('role', 'display')
                ->delete();

            DB::table('tournament_user')->insertOrIgnore([
                'tournament_id' => $display->tournament_id,
                'user_id' => $display->user_id,
                'role' => 'operator',
                'created_at' => $display->created_at ?? now(),
                'updated_at' => $display->updated_at ?? now(),
            ]);
        }

        $this->setRoles(['manager', 'operator', 'judge']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setRoles(['manager', 'operator', 'judge', 'display']);
    }

    private function setRoles(array $roles): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tournament_user DROP CONSTRAINT IF EXISTS tournament_user_role_check');
            DB::statement("ALTER TABLE tournament_user ADD CONSTRAINT tournament_user_role_check CHECK (role IN ('".implode("', '", $roles)."'))");

            return;
        }

        Schema::table('tournament_user', function (Blueprint $table) use ($roles): void {
            $table->enum('role', $roles)->change();
        });
    }
};
