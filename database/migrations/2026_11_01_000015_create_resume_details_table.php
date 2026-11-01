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
        // Resume-only details for the resume builder. Everything else on the
        // resume comes from the applicant profile.
        Schema::create('resume_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('desired_position', 150)->nullable();
            $table->text('objective')->nullable();
            $table->json('languages')->nullable();
            $table->json('references')->nullable();
            $table->string('civil_status', 20)->nullable();
            $table->string('nationality', 60)->nullable();
            $table->boolean('show_birthdate')->default(false);
            $table->boolean('show_sex')->default(false);
            $table->boolean('include_certification')->default(true);
            $table->timestamps();
        });

        Schema::table('resumes', function (Blueprint $table) {
            $table->boolean('is_generated')->default(false)->after('size_bytes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('is_generated');
        });

        Schema::dropIfExists('resume_details');
    }
};
