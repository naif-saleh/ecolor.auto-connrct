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
        Schema::table('auto_dailer_files', function (Blueprint $table) {
            $table->time('from')->nullable();
            $table->time('to')->nullable();
            $table->date('date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_dailer_files', function (Blueprint $table) {
            $table->dropColumn(['from', 'to', 'date']);
        });
    }
};
