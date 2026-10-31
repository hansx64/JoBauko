<?php

namespace Database\Factories;

use App\Enums\ResumeExtractionStatus;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Resume>
 */
class ResumeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'disk_path' => 'resumes/test/'.Str::uuid().'.pdf',
            'original_name' => 'resume.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'is_current' => true,
            'extraction_status' => ResumeExtractionStatus::Pending,
        ];
    }
}
