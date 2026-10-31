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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 20);
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->text('qualifications')->nullable();
            $table->text('benefits')->nullable();
            $table->text('application_instructions')->nullable();
            $table->string('min_education', 30)->nullable();
            // Bauko barangay when the opportunity is local; free text otherwise.
            $table->foreignId('barangay_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('location_text')->nullable();
            $table->boolean('is_remote')->default(false);
            $table->date('application_start')->nullable();
            // Nullable for drafts; required by validation before publishing.
            $table->date('application_deadline')->nullable();
            $table->unsignedInteger('slots')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category', 'application_deadline']);
            $table->index(['organization_id', 'status']);
            $table->fullText(['title', 'description', 'qualifications']);
        });

        Schema::create('job_details', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('employment_type', 30);
            $table->decimal('salary_min', 12, 2)->nullable();
            $table->decimal('salary_max', 12, 2)->nullable();
            $table->string('work_setup', 20)->default('onsite');
            $table->unsignedTinyInteger('min_experience_years')->nullable();
            $table->timestamps();
        });

        Schema::create('scholarship_details', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('education_level', 30);
            $table->json('eligible_courses')->nullable();
            $table->text('academic_requirements')->nullable();
            $table->text('financial_requirements')->nullable();
            $table->timestamps();
        });

        Schema::create('livelihood_details', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('assistance_type', 30);
            $table->text('target_beneficiaries')->nullable();
            $table->text('assistance_provided')->nullable();
            $table->text('training_requirements')->nullable();
            $table->json('target_classifications')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunity_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('opportunity_skills', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(true);

            $table->primary(['opportunity_id', 'skill_id']);
        });

        Schema::create('opportunity_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_required')->default(true);
            $table->string('accepted_types', 100)->default('pdf,docx,jpg,png');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_documents');
        Schema::dropIfExists('opportunity_skills');
        Schema::dropIfExists('opportunity_requirements');
        Schema::dropIfExists('livelihood_details');
        Schema::dropIfExists('scholarship_details');
        Schema::dropIfExists('job_details');
        Schema::dropIfExists('opportunities');
    }
};
