<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A mobile number may be on one account of each type, so a resident's
     * job seeker number can also be used for the organization they register.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mobile_number']);
            $table->unique(['account_type', 'mobile_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['account_type', 'mobile_number']);
            $table->unique('mobile_number');
        });
    }
};
