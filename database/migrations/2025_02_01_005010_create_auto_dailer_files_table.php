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
        Schema::create('auto_dailer_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->time('from')->nullable();
            $table->time('to')->nullable();
            $table->date('date')->nullable();
            $table->boolean('allow')->default(false);
            $table->boolean('is_done')->default(false);
            $table->string('slug')->unique();
            $table->unsignedBigInteger('uploaded_by'); // Make sure this comes first
            $table->unsignedBigInteger('provider_id'); // Then add provider_id
            $table->timestamps();

            // Foreign keys
            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('provider_id')->references('id')->on('auto_dialer_providers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dailer_files');
    }
};
