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
        Schema::create('auto_dailer_feed_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');  
            $table->string('extension');
            $table->time('from');
            $table->time('to');
            $table->date('date');
            $table->integer('on')->default(1);


            $table->string('file_name');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('provider_id')->references('id')->on('auto_dialer_providers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dailer_feed_files');
    }
};
