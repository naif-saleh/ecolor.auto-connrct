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
        Schema::table('auto_distributer_reports', function (Blueprint $table) {
            $table->string('duration_time')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_distributer_reports', function (Blueprint $table) {
            $table->dropColumn('duration_time');
        });
    }
};
