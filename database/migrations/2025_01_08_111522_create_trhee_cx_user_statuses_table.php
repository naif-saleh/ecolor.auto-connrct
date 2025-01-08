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
        Schema::create('trhee_cx_user_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('firstName');
            $table->string('lastName');
            $table->string('displayName')->nullable();
            $table->string('email')->nullable();
            $table->boolean('isRegistred');
            $table->string('QueueStatus');
            $table->string('extension');
            $table->string('status');

            // $table->unsignedBigInteger('csv_file_id');
            // $table->foreign('csv_file_id')->references('id')->on('auto_distributor_uploaded_data')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trhee_cx_user_statuses');
    }
};
