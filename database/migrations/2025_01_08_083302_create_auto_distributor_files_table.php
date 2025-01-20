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
        Schema::create('auto_distributor_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->time('from')->nullable();
            $table->time('to')->nullable();
            $table->date('date')->nullable();
            $table->boolean('allow')->default(false);
            $table->boolean('is_done')->default(false);
            $table->string('slug')->unique();
            $table->unsignedBigInteger('uploaded_by');
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_distributor_files');
    }
};
