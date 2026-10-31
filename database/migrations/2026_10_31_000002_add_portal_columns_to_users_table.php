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
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile_number', 11)->nullable()->unique()->after('email');
            $table->timestamp('mobile_verified_at')->nullable()->after('email_verified_at');
            $table->string('account_type', 20)->default('seeker')->after('password');
            $table->foreignId('barangay_id')->nullable()->after('account_type')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('barangay_id');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
            $table->string('deactivation_reason')->nullable()->after('deactivated_at');
            $table->timestamp('last_login_at')->nullable()->after('deactivation_reason');
            $table->softDeletes();

            $table->index(['account_type', 'is_active']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_type', 'is_active']);
            $table->dropIndex(['created_at']);
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropUnique(['mobile_number']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'mobile_number',
                'mobile_verified_at',
                'account_type',
                'is_active',
                'deactivated_at',
                'deactivation_reason',
                'last_login_at',
            ]);
        });
    }
};
