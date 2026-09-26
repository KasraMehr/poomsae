<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bouts', function (Blueprint $table) {
            $table->foreignId('winner_entry_id')->nullable()->constrained('entries')->restrictOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('resolution_reason', 1000)->nullable();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->json('form_sequence')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('form_sequence'));
        Schema::table('bouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_entry_id');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropColumn('resolution_reason');
        });
    }
};
