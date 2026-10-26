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
        Schema::table('categories', function (Blueprint $table): void {
            $table->enum('judge_count', ['3', '5', '7'])->change();
        });
        DB::table('competition_rounds')->orderBy('id')->each(function (object $round): void {
            DB::table('competition_rounds')->where('id', $round->id)->update(['name' => 'مرحله '.$round->sequence]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->enum('judge_count', ['5', '7'])->change();
        });
    }
};
