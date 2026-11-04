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
        $this->setRoles(['manager', 'operator', 'judge', 'display', 'supervisor']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('tournament_user')->where('role', 'supervisor')->exists()) {
            throw new RuntimeException('Remove supervisor assignments explicitly before rolling back this migration.');
        }

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
