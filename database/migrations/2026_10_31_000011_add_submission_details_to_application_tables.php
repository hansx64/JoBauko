<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The applicant's consent to share their profile and files with the
     * organization is recorded per application (Data Privacy Act). Document
     * labels are copied so they survive later edits of the opportunity.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->timestamp('data_sharing_consented_at')->nullable()->after('cover_letter');
            $table->text('withdrawal_reason')->nullable()->after('withdrawn_at');
        });

        Schema::table('application_documents', function (Blueprint $table) {
            $table->string('label', 150)->after('opportunity_document_id');
            $table->foreignId('uploaded_by')->nullable()->after('size_bytes')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropColumn('label');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['data_sharing_consented_at', 'withdrawal_reason']);
        });
    }
};
