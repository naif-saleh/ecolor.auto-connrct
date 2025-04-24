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
        Schema::create('a_dist_webhook_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->string('status')->default('received'); // received, processing, completed, failed
            $table->integer('total_numbers')->default(0);
            $table->integer('processed_numbers')->default(0);
            $table->integer('skipped_numbers')->default(0);
            $table->boolean('is_last_batch')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('errors')->nullable();
            $table->timestamps();
        });

        // Add webhook_batch_id to skipped numbers table
        Schema::table('a_dist_skipped_numbers', function (Blueprint $table) {
            $table->foreignId('webhook_batch_id')->nullable()->constrained('a_dist_webhook_batches');
        });

        // Add webhook_batch_id to feeds table
        Schema::table('a_dist_feeds', function (Blueprint $table) {
            $table->foreignId('webhook_batch_id')->nullable()->constrained('a_dist_webhook_batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('a_dist_skipped_numbers', function (Blueprint $table) {
            $table->dropForeign(['webhook_batch_id']);
            $table->dropColumn('webhook_batch_id');
        });

        Schema::table('a_dist_feeds', function (Blueprint $table) {
            $table->dropForeign(['webhook_batch_id']);
            $table->dropColumn('webhook_batch_id');
        });

        Schema::dropIfExists('a_dist_webhook_batches');    }
};
