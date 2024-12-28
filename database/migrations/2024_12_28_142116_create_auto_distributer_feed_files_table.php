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
        Schema::create('auto_distributer_feed_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_ext_id');
            $table->string('extension');
            $table->time('from');
            $table->time('to');
            $table->date('date');
            $table->integer('on')->default(1);
            $table->string('file_name');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_ext_id')->references('id')->on('auto_distributerer_extensions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_distributer_feed_files');
    }
};
