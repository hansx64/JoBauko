<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drafts may be saved before a description is written; publishing requires
 * one (enforced by OpportunityRequest and OpportunityService).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
        });
    }
};
