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
        Schema::create('auto_distributor_uploaded_data', function (Blueprint $table) {
            $table->id();
            $table->string('mobile');
            $table->string('user');
            $table->string('userStatus')->nullable();
            $table->string('extension')->nullable();
            $table->time('from');
            $table->time('to');
            $table->date('date');
            $table->string('state')->default('new');
            $table->string('call_date')->nullable();
            $table->string('call_id')->nullable();
            $table->string('three_cx_user_id')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedBigInteger('file_id');
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('file_id')->references('id')->on('auto_distributor_files')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_distributor_uploaded_data');
    }
};
