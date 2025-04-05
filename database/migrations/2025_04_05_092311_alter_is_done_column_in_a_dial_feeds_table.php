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
        Schema::table('a_dial_feeds', function (Blueprint $table) {
            $table->string('is_done')->default('false')->change(); // Change boolean to string
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('a_dial_feeds', function (Blueprint $table) {
            $table->boolean('is_done')->default(false)->change(); // Revert back to boolean if rolling back
        });
    }
};
