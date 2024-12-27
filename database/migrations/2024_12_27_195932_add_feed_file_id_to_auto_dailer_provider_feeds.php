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
        Schema::table('auto_dailer_provider_feeds', function (Blueprint $table) {
            $table->unsignedBigInteger('auto_dailer_feed_file_id')->nullable();

            // Foreign key constraint to the 'auto_dailer_feed_files' table
            $table->foreign('auto_dailer_feed_file_id')
                  ->references('id')->on('auto_dailer_feed_files')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_dailer_provider_feeds', function (Blueprint $table) {
            $table->dropForeign(['auto_dailer_feed_file_id']);
            $table->dropColumn('auto_dailer_feed_file_id');
        });
    }
};
