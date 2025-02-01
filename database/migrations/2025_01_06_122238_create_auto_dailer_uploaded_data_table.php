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
        Schema::create('auto_dailer_uploaded_data', function (Blueprint $table) {
            $table->id();
            $table->string('mobile');
            $table->string('provider');
            $table->string('extension')->nullable();
            $table->string('state')->default('new');
            $table->string('call_date')->nullable();
            $table->string('call_id')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->unsignedBigInteger('file_id'); // Foreign key reference to auto_dailer_files
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
           // $table->foreign('file_id')->references('id')->on('auto_dailer_files')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dailer_uploaded_data');
    }
};
