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
        Schema::table('opportunities', function (Blueprint $table) {
            // Reinstating restores the status the listing had before.
            $table->string('status_before_suspension', 20)->nullable()->after('suspension_reason');
            $table->timestamp('suspended_at')->nullable()->after('status_before_suspension');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('deactivated_by')->nullable()->after('deactivation_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->string('outcome', 30)->nullable()->after('status');
            $table->index(['reportable_type', 'reportable_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['reportable_type', 'reportable_id', 'status']);
            $table->dropColumn('outcome');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deactivated_by');
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['status_before_suspension', 'suspended_at']);
        });
    }
};
