<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('auto_dailer_uploaded_data', function (Blueprint $table) {
            if (Schema::hasColumn('auto_dailer_uploaded_data', 'from')) {
                $table->dropColumn('from');
            }
            if (Schema::hasColumn('auto_dailer_uploaded_data', 'to')) {
                $table->dropColumn('to');
            }
            if (Schema::hasColumn('auto_dailer_uploaded_data', 'date')) {
                $table->dropColumn('date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_dailer_uploaded_data', function (Blueprint $table) {
            if (!Schema::hasColumn('auto_dailer_uploaded_data', 'from')) {
                $table->time('from')->nullable();
            }
            if (!Schema::hasColumn('auto_dailer_uploaded_data', 'to')) {
                $table->time('to')->nullable();
            }
            if (!Schema::hasColumn('auto_dailer_uploaded_data', 'date')) {
                $table->date('date')->nullable();
            }
        });
    }
};
