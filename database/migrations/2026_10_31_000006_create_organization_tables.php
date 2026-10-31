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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 30);
            $table->string('official_email');
            $table->string('contact_number', 20);
            $table->string('office_address');
            $table->foreignId('barangay_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('representative_name');
            $table->string('representative_position')->nullable();
            $table->string('verification_status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('status_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->boolean('is_active')->default(true);
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'role']);
        });

        Schema::create('organization_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->string('disk_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_documents');
        Schema::dropIfExists('organization_members');
        Schema::dropIfExists('organizations');
    }
};
