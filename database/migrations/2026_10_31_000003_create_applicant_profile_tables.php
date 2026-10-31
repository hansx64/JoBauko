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
        Schema::create('applicant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('classification', 30)->index();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('address_line')->nullable();
            $table->string('highest_education', 30)->nullable();
            $table->string('current_education_status', 30)->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('level', 30);
            $table->string('school');
            $table->string('course')->nullable();
            $table->unsignedSmallInteger('year_graduated')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('applicant_experience', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('position');
            $table->string('company');
            $table->string('industry')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('provider')->nullable();
            $table->date('completed_at')->nullable();
            $table->unsignedSmallInteger('hours')->nullable();
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 120)->unique();
            $table->string('type', 20)->default('technical');
            $table->timestamps();
        });

        Schema::create('applicant_skills', function (Blueprint $table) {
            $table->foreignId('applicant_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->primary(['applicant_profile_id', 'skill_id']);
        });

        Schema::create('applicant_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_profile_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('field', 100)->nullable();
            $table->timestamps();

            $table->unique(['applicant_profile_id', 'category', 'field']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applicant_interests');
        Schema::dropIfExists('applicant_skills');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('applicant_trainings');
        Schema::dropIfExists('applicant_certifications');
        Schema::dropIfExists('applicant_experience');
        Schema::dropIfExists('applicant_education');
        Schema::dropIfExists('applicant_profiles');
    }
};
