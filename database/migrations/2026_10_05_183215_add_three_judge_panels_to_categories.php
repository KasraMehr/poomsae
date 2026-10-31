<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->setJudgeCounts(['3', '5', '7']);
        DB::table('competition_rounds')->orderBy('id')->each(function (object $round): void {
            DB::table('competition_rounds')->where('id', $round->id)->update(['name' => 'مرحله '.$round->sequence]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->setJudgeCounts(['5', '7']);
    }

    private function setJudgeCounts(array $counts): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE categories DROP CONSTRAINT IF EXISTS categories_judge_count_check');
            DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_judge_count_check CHECK (judge_count IN ('".implode("', '", $counts)."'))");

            return;
        }

        Schema::table('categories', function (Blueprint $table) use ($counts): void {
            $table->enum('judge_count', $counts)->change();
        });
    }
};
