<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('score_sheets', function (Blueprint $table) {
            $table->json('breakdown')->nullable()->after('revision');
        });
    }

    public function down(): void
    {
        Schema::table('score_sheets', function (Blueprint $table) {
            $table->dropColumn('breakdown');
        });
    }
};
