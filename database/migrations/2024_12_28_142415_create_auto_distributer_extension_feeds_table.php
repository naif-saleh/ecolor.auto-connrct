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
        Schema::create('auto_distributer_extension_feeds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_ext_id');
            $table->string('mobile');
            $table->string('state')->default('new');
            $table->timestamps();
            $table->unsignedBigInteger('auto_dist_feed_file_id')->nullable();


            $table->foreign('auto_dist_feed_file_id')
                  ->references('id')->on('auto_distributer_feed_files')
                  ->onDelete('cascade');

            $table->foreign('user_ext_id')->references('id')->on('auto_distributerer_extensions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_distributer_extension_feeds');
    }
};
