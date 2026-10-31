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
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('announcement_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->timestamp('sending_started_at')->nullable()->after('recipient_count');
        });

        // Automatic notices (deadline reminders, new opportunity alerts) are
        // sent at most once per key, even if a scheduled run repeats.
        Schema::create('notification_dedupes', function (Blueprint $table) {
            $table->string('key', 150)->primary();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dedupes');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('sending_started_at');
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('announcement_id');
        });
    }
};
