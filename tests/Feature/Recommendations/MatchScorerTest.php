<?php

namespace Tests\Feature\Recommendations;

use App\Enums\AssistanceType;
use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Skill;
use App\Models\User;
use App\Services\Recommendation\MatchScorer;
use App\Services\Recommendation\SeekerSnapshot;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchScorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LocationSeeder::class);
    }

    private function seeker(Classification $classification, ?EducationLevel $education, array $skills = [], ?string $course = null, ?string $barangay = 'Poblacion'): User
    {
        $user = User::factory()->seeker()->create(['barangay_id' => Barangay::where('name', $barangay)->value('id')]);
        $profile = $user->applicantProfile;
        $profile->update(['classification' => $classification, 'highest_education' => $education]);

        foreach ($skills as $name) {
            $profile->skills()->attach(Skill::findOrCreateByName($name)->id);
        }

        if ($course) {
            $profile->education()->create(['level' => $education ?? EducationLevel::CollegeGraduate, 'school' => 'MPSPC', 'course' => $course]);
        }

        return $user->fresh();
    }

    private function itJob(): Opportunity
    {
        $job = Opportunity::factory()->published()->create([
            'title' => 'Municipal IT Assistant',
            'min_education' => EducationLevel::CollegeGraduate,
            'barangay_id' => Barangay::where('name', 'Poblacion')->value('id'),
            'location_text' => null,
        ]);
        $job->jobDetail()->create(['employment_type' => EmploymentType::Contractual]);
        $job->skills()->attach([
            Skill::findOrCreateByName('Networking')->id => ['is_required' => true],
            Skill::findOrCreateByName('Technical Support')->id => ['is_required' => true],
            Skill::findOrCreateByName('Microsoft Excel')->id => ['is_required' => true],
        ]);

        return $job->load(['skills', 'barangay', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail']);
    }

    private function score(User $user, Opportunity $opportunity)
    {
        return app(MatchScorer::class)->score(SeekerSnapshot::fromUser($user), $opportunity);
    }

    public function test_an_it_graduate_with_the_skills_scores_high_with_reasons()
    {
        $graduate = $this->seeker(Classification::FreshGraduate, EducationLevel::CollegeGraduate, ['Networking', 'Technical Support'], 'BS Information Technology');

        $result = $this->score($graduate, $this->itJob());

        $this->assertGreaterThanOrEqual(75, $result->score);
        $this->assertContains('You have 2 of 3 listed skills: Networking, Technical Support', $result->reasons);
        $this->assertContains("Your education (College Graduate (Bachelor's)) meets the College Graduate (Bachelor's) requirement", $result->reasons);
        $this->assertContains('Related to your course BS Information Technology', $result->reasons);
        $this->assertContains('In your barangay, Poblacion', $result->reasons);
        $this->assertStringContainsString('Microsoft Excel', implode(' ', $result->gaps));
    }

    public function test_an_unrelated_profile_scores_low_and_is_told_what_is_missing()
    {
        $student = $this->seeker(Classification::Student, EducationLevel::SeniorHigh, ['Weaving'], null, 'Sinto');

        $result = $this->score($student, $this->itJob());

        $this->assertLessThan(MatchScorer::RECOMMENDATION_THRESHOLD, $result->score);
        $this->assertContains("Requires College Graduate (Bachelor's); your profile shows Senior High School.", $result->gaps);
    }

    public function test_scores_are_deterministic()
    {
        $graduate = $this->seeker(Classification::FreshGraduate, EducationLevel::CollegeGraduate, ['Networking']);
        $job = $this->itJob();

        $this->assertSame($this->score($graduate, $job)->score, $this->score($graduate, $job)->score);
    }

    public function test_unspecified_criteria_are_left_out_instead_of_penalizing()
    {
        $seeker = $this->seeker(Classification::Unemployed, null);
        $job = Opportunity::factory()->published()->create(['min_education' => null, 'location_text' => null, 'is_remote' => true]);
        $job->jobDetail()->create(['employment_type' => EmploymentType::FullTime]);
        $job->load(['skills', 'barangay', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail']);

        $result = $this->score($seeker, $job);

        // No skills or education requirement: no "missing skills/education" gaps.
        $this->assertEmpty(array_filter($result->gaps, fn ($gap) => str_contains($gap, 'skills') || str_contains($gap, 'Requires')));
        $this->assertContains('Can be done remotely', $result->reasons);
    }

    public function test_students_match_scholarships_for_their_course()
    {
        $student = $this->seeker(Classification::Student, EducationLevel::SeniorHigh, [], 'BS Agriculture');
        $scholarship = Opportunity::factory()->published()->create(['category' => OpportunityCategory::Scholarship, 'title' => 'Agri Scholarship']);
        $scholarship->scholarshipDetail()->create([
            'education_level' => EducationLevel::CollegeLevel,
            'eligible_courses' => ['BS Agriculture', 'BS Forestry'],
        ]);
        $scholarship->load(['skills', 'barangay', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail']);

        $result = $this->score($student, $scholarship);

        $this->assertContains('Scholarships support students like you', $result->reasons);
        $this->assertContains('Your course “BS Agriculture” matches the eligible course “BS Agriculture”', $result->reasons);
        $this->assertGreaterThanOrEqual(MatchScorer::RECOMMENDATION_THRESHOLD, $result->score);
    }

    public function test_livelihood_programs_favor_their_target_groups()
    {
        $program = Opportunity::factory()->published()->create(['category' => OpportunityCategory::Livelihood, 'title' => 'Weaving starter kits']);
        $program->livelihoodDetail()->create([
            'assistance_type' => AssistanceType::StarterKit,
            'target_classifications' => [Classification::OutOfSchoolYouth->value],
        ]);
        $program->load(['skills', 'barangay', 'jobDetail', 'scholarshipDetail', 'livelihoodDetail']);

        $youth = $this->seeker(Classification::OutOfSchoolYouth, EducationLevel::JuniorHigh);
        $employed = $this->seeker(Classification::Employed, EducationLevel::JuniorHigh);

        $this->assertContains('This program is meant for Out-of-School Youth residents', $this->score($youth, $program)->reasons);
        $this->assertGreaterThan($this->score($employed, $program)->score, $this->score($youth, $program)->score);
    }
}
