<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The applicant's match score when they applied, so organizations can
     * sort applicants by it. The review page always shows the live score.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('match_score')->nullable()->after('status');
            $table->index(['opportunity_id', 'match_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['opportunity_id', 'match_score']);
            $table->dropColumn('match_score');
        });
    }
};
