<?php

namespace Tests\Feature\Seeker;

use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EducationStatus;
use App\Models\ApplicantEducation;
use App\Models\Barangay;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicantProfileTest extends TestCase
{
    use RefreshDatabase;

    private function seeker(): User
    {
        return User::factory()->seeker()->create();
    }

    public function test_profile_page_renders_with_completion()
    {
        $this->actingAs($this->seeker())
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('seeker/profile')
                ->where('completion.percent', 0)
                ->has('completion.sections', 7)
                ->has('options.educationLevels', count(EducationLevel::cases())));
    }

    public function test_non_seekers_cannot_open_the_profile_area()
    {
        $this->actingAs(User::factory()->admin()->create())->get('/profile')->assertForbidden();
    }

    public function test_personal_information_and_classification_can_be_updated()
    {
        $user = $this->seeker();

        $this->actingAs($user)->patch('/profile', [
            'classification' => Classification::CareerShifter->value,
            'date_of_birth' => '1998-04-12',
            'gender' => 'female',
            'address_line' => 'Sitio Lamagan',
            'highest_education' => 'college_graduate',
            'current_education_status' => 'completed',
            'summary' => 'Looking for IT work.',
        ])->assertSessionHasNoErrors();

        $profile = $user->applicantProfile->fresh();
        $this->assertSame(Classification::CareerShifter, $profile->classification);
        $this->assertSame('1998-04-12', $profile->date_of_birth->toDateString());
        $this->assertSame(EducationLevel::CollegeGraduate, $profile->highest_education);
    }

    public function test_college_graduates_and_above_are_recorded_as_finished_studying()
    {
        $user = $this->seeker();
        $this->actingAs($user);

        // The form hides the status for graduates, so none is sent.
        $this->patch('/profile', ['classification' => 'unemployed', 'highest_education' => 'masters'])->assertSessionHasNoErrors();
        $this->assertSame(EducationStatus::Completed, $user->applicantProfile->fresh()->current_education_status);

        $this->patch('/profile', ['classification' => 'student', 'highest_education' => 'college_level', 'current_education_status' => 'studying']);
        $this->assertSame(EducationStatus::Studying, $user->applicantProfile->fresh()->current_education_status);
    }

    public function test_changing_classification_does_not_change_notification_preferences()
    {
        $this->seed(LocationSeeder::class);
        $this->post('/register', [
            'name' => 'Ana Reyes',
            'email' => 'ana@example.com',
            'mobile_number' => '09181234567',
            'barangay_id' => Barangay::value('id'),
            'classification' => 'student',
            'password' => 'Highland-Harvest-2026',
            'password_confirmation' => 'Highland-Harvest-2026',
            'privacy_consent' => true,
        ]);
        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $user->markEmailAsVerified();
        $before = $user->notificationPreferences()->orderBy('topic')->get(['topic', 'sms'])->toArray();

        $this->actingAs($user)->patch('/profile', ['classification' => 'unemployed']);

        $this->assertSame($before, $user->notificationPreferences()->orderBy('topic')->get(['topic', 'sms'])->toArray());
    }

    public function test_education_entries_can_be_added_updated_and_removed()
    {
        $user = $this->seeker();
        $this->actingAs($user);

        $this->post('/profile/education', [
            'level' => 'college_graduate',
            'school' => 'Benguet State University',
            'course' => 'BS Agriculture',
            'year_graduated' => 2020,
            'is_current' => false,
        ])->assertSessionHasNoErrors();

        $entry = $user->applicantProfile->education()->sole();

        $this->patch("/profile/education/{$entry->id}", [
            'level' => 'college_graduate',
            'school' => 'Benguet State University',
            'course' => 'BS Agribusiness',
            'year_graduated' => 2021,
        ])->assertSessionHasNoErrors();

        $this->assertSame('BS Agribusiness', $entry->fresh()->course);

        $this->delete("/profile/education/{$entry->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($entry);
    }

    public function test_seekers_cannot_modify_another_seekers_entries()
    {
        $other = $this->seeker();
        $entry = $other->applicantProfile->education()->create(['level' => 'senior_high', 'school' => 'Bauko NHS']);

        $this->actingAs($this->seeker());

        $this->patch("/profile/education/{$entry->id}", ['level' => 'elementary', 'school' => 'Changed'])->assertNotFound();
        $this->delete("/profile/education/{$entry->id}")->assertNotFound();

        $this->assertSame('Bauko NHS', ApplicantEducation::find($entry->id)->school);
    }

    public function test_experience_validates_date_order()
    {
        $this->actingAs($this->seeker())
            ->post('/profile/experience', [
                'position' => 'Farm Hand',
                'company' => 'Family farm',
                'start_date' => '2022-05-01',
                'end_date' => '2021-01-01',
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_certifications_and_trainings_can_be_added()
    {
        $user = $this->seeker();
        $this->actingAs($user);

        $this->post('/profile/certifications', ['name' => 'Bread and Pastry Production NC II', 'issuer' => 'TESDA'])->assertSessionHasNoErrors();
        $this->post('/profile/trainings', ['title' => 'Organic Farming Training', 'provider' => 'ATI', 'completed_at' => '2025-06-20', 'hours' => 24])->assertSessionHasNoErrors();

        $this->assertSame(1, $user->applicantProfile->certifications()->count());
        $this->assertSame(24, $user->applicantProfile->trainings()->sole()->hours);
    }

    public function test_a_training_needs_who_conducted_it_when_and_for_how_long()
    {
        $this->actingAs($this->seeker())
            ->post('/profile/trainings', ['title' => 'Organic Farming Training'])
            ->assertSessionHasErrors(['provider', 'completed_at', 'hours']);

        $this->post('/profile/trainings', ['title' => 'Organic Farming Training', 'provider' => 'ATI', 'completed_at' => now()->addDay()->toDateString(), 'hours' => 24])
            ->assertSessionHasErrors(['completed_at' => 'The completion date cannot be in the future.']);
    }

    public function test_skills_are_synced_against_the_dictionary_without_duplicates()
    {
        $this->seed(SkillSeeder::class);
        $user = $this->seeker();

        $this->actingAs($user)
            ->put('/profile/skills', ['skills' => ['organic farming', 'Weaving', 'Kankanaey Translation']])
            ->assertSessionHasNoErrors();

        $skills = $user->applicantProfile->skills()->pluck('name')->sort()->values()->all();

        // Known skills keep their dictionary spelling; new ones are added.
        $this->assertSame(['Kankanaey Translation', 'Organic Farming', 'Weaving'], $skills);

        $this->put('/profile/skills', ['skills' => ['Weaving']]);
        $this->assertSame(['Weaving'], $user->applicantProfile->skills()->pluck('name')->all());
    }

    public function test_duplicate_skills_in_one_request_are_rejected()
    {
        $this->actingAs($this->seeker())
            ->put('/profile/skills', ['skills' => ['Weaving', 'weaving']])
            ->assertSessionHasErrors('skills.1');
    }

    public function test_interests_are_replaced()
    {
        $user = $this->seeker();
        $this->actingAs($user);

        $this->put('/profile/interests', ['interests' => [
            ['category' => 'job', 'field' => 'Information Technology'],
            ['category' => 'livelihood', 'field' => 'Agriculture'],
        ]])->assertSessionHasNoErrors();

        $this->put('/profile/interests', ['interests' => [['category' => 'scholarship', 'field' => null]]]);

        $this->assertSame(['scholarship'], $user->applicantProfile->interests()->pluck('category')->map->value->all());
    }

    public function test_completion_reflects_filled_sections()
    {
        $this->seed(SkillSeeder::class);
        $user = $this->seeker();
        $this->actingAs($user);

        $this->put('/profile/skills', ['skills' => ['Weaving']]);
        $this->put('/profile/interests', ['interests' => [['category' => 'livelihood', 'field' => 'Weaving']]]);

        $this->get('/profile')->assertInertia(fn ($page) => $page->where('completion.percent', 30));
    }
}
