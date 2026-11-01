<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trainings and seminars become their own opportunity category. Livelihood
     * programs posted as "Skills training" are moved into it.
     */
    public function up(): void
    {
        Schema::create('training_details', function (Blueprint $table) {
            $table->foreignId('opportunity_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('training_type', 30);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('schedule')->nullable();
            $table->unsignedSmallInteger('duration_hours')->nullable();
            // Null or zero means free.
            $table->decimal('fee', 10, 2)->nullable();
            $table->string('certificate')->nullable();
            $table->text('topics')->nullable();
            $table->text('inclusions')->nullable();
            $table->text('target_participants')->nullable();
            $table->json('target_classifications')->nullable();
            $table->timestamps();
        });

        DB::transaction(function () {
            $trainings = DB::table('livelihood_details')->where('assistance_type', 'skills_training')->get();

            foreach ($trainings as $livelihood) {
                DB::table('training_details')->insert([
                    'opportunity_id' => $livelihood->opportunity_id,
                    'training_type' => 'skills_training',
                    'topics' => $livelihood->training_requirements,
                    'inclusions' => $livelihood->assistance_provided,
                    'target_participants' => $livelihood->target_beneficiaries,
                    'target_classifications' => $livelihood->target_classifications,
                    'created_at' => $livelihood->created_at,
                    'updated_at' => now(),
                ]);
            }

            $ids = $trainings->pluck('opportunity_id');
            DB::table('opportunities')->whereIn('id', $ids)->update(['category' => 'training']);
            DB::table('livelihood_details')->whereIn('opportunity_id', $ids)->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::transaction(function () {
            foreach (DB::table('training_details')->get() as $training) {
                DB::table('livelihood_details')->insert([
                    'opportunity_id' => $training->opportunity_id,
                    'assistance_type' => 'skills_training',
                    'target_beneficiaries' => $training->target_participants,
                    'assistance_provided' => $training->inclusions,
                    'training_requirements' => $training->topics,
                    'target_classifications' => $training->target_classifications,
                    'created_at' => $training->created_at,
                    'updated_at' => now(),
                ]);
            }

            DB::table('opportunities')->where('category', 'training')->update(['category' => 'livelihood']);
        });

        Schema::dropIfExists('training_details');
    }
};
