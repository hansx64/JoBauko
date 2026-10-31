<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\AssistanceType;
use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\InterestCategory;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\VerificationStatus;
use App\Enums\WorkSetup;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Services\Notifications\NotificationPreferenceService;
use App\Services\Recommendation\MatchRefresher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sample organizations, opportunities and seekers for development and
 * demonstrations. Every record is labelled "(Demo)"; never run in production.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Demo logins (password "password"):
 *   demo.seeker@jobauko.test   – fresh IT graduate
 *   demo.student@jobauko.test  – agriculture student
 *   demo.org@jobauko.test      – organization administrator
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must not be seeded in production.');
        }

        $this->call([LocationSeeder::class, SkillSeeder::class]);

        $barangay = fn (string $name) => Barangay::where('name', $name)->value('id');

        $lgu = $this->organization('Bauko Municipal Office (Demo)', OrganizationType::Lgu, 'Poblacion', 'The municipal government of Bauko, Mountain Province.');
        $coop = $this->organization('Highland Growers Cooperative (Demo)', OrganizationType::Ngo, 'Abatan', 'A cooperative of highland vegetable farmers in Bauko.');
        $cafe = $this->organization('Cordillera Coffee Roasters (Demo)', OrganizationType::PrivateCompany, null, 'A coffee roastery sourcing beans from Mountain Province farms.');

        $this->member($lgu, 'demo.org@jobauko.test', 'Rosa Dalang (Demo)');

        $this->opportunity($lgu, OpportunityCategory::Job, 'Municipal IT Assistant', [
            'description' => "Support the municipal hall's computers, printers and network, and help staff with everyday technical problems.",
            'qualifications' => 'Graduate of Information Technology, Computer Science or a related course.',
            'min_education' => EducationLevel::CollegeGraduate,
            'barangay_id' => $barangay('Poblacion'),
            'slots' => 1,
            'benefits' => 'Contract of service with possibility of renewal.',
        ], ['employment_type' => EmploymentType::Contractual, 'salary_min' => 18000, 'salary_max' => 22000, 'work_setup' => WorkSetup::Onsite, 'min_experience_years' => 0],
            ['Networking', 'Technical Support', 'Computer Hardware Servicing', 'Microsoft Office'], ['Resume / CV', 'Transcript of Records']);

        $this->opportunity($lgu, OpportunityCategory::Scholarship, 'Bauko Municipal College Scholarship', [
            'description' => 'Financial assistance for deserving college students from Bauko, covering tuition and a monthly allowance.',
            'qualifications' => 'Resident of Bauko for at least two years; enrolled or incoming college student.',
            'min_education' => EducationLevel::SeniorHigh,
            'barangay_id' => $barangay('Poblacion'),
            'slots' => 30,
            'benefits' => 'Full tuition and ₱2,000 monthly allowance.',
        ], ['education_level' => EducationLevel::CollegeLevel, 'eligible_courses' => ['BS Agriculture', 'BS Forestry', 'BS Education', 'BS Information Technology'], 'academic_requirements' => 'General weighted average of at least 85%.', 'financial_requirements' => 'Combined family income below ₱250,000 a year.'],
            [], ['Certificate of Grades', 'Certificate of Indigency', 'Barangay Clearance']);

        $this->opportunity($coop, OpportunityCategory::Livelihood, 'Highland Vegetable Starter Kit Program', [
            'description' => 'Seeds, organic fertilizer and basic farm tools for new or returning vegetable growers, with technical support from the cooperative.',
            'barangay_id' => $barangay('Abatan'),
            'slots' => 50,
        ], ['assistance_type' => AssistanceType::FarmInputs, 'target_beneficiaries' => 'Unemployed residents, out-of-school youth and small farmers of Bauko.', 'assistance_provided' => 'Vegetable seeds, organic fertilizer and a set of farm tools.', 'training_requirements' => 'Attend a one-day orientation on organic farming.', 'target_classifications' => [Classification::Unemployed->value, Classification::OutOfSchoolYouth->value, Classification::SelfEmployed->value]],
            ['Highland Vegetable Farming', 'Organic Farming'], ['Barangay Certificate of Residency']);

        $this->opportunity($coop, OpportunityCategory::Job, 'Farm Technician', [
            'description' => "Assist member farmers with planting schedules, pest management and record keeping across the cooperative's farms.",
            'qualifications' => 'Background in agriculture or practical farming experience.',
            'min_education' => EducationLevel::Vocational,
            'barangay_id' => $barangay('Abatan'),
            'slots' => 2,
        ], ['employment_type' => EmploymentType::FullTime, 'salary_min' => 14000, 'salary_max' => 16000, 'work_setup' => WorkSetup::Onsite, 'min_experience_years' => 1],
            ['Highland Vegetable Farming', 'Crop Production', 'Organic Farming'], ['Resume / CV']);

        $this->opportunity($coop, OpportunityCategory::Livelihood, 'Traditional Weaving Skills Training', [
            'description' => 'A four-week hands-on training on traditional Cordillera weaving, with a starter loom for graduates.',
            'barangay_id' => $barangay('Sadsadan'),
            'slots' => 20,
        ], ['assistance_type' => AssistanceType::SkillsTraining, 'target_beneficiaries' => 'Women and out-of-school youth interested in weaving.', 'assistance_provided' => 'Free training, materials and a starter loom.', 'target_classifications' => [Classification::OutOfSchoolYouth->value, Classification::Unemployed->value]],
            ['Weaving', 'Handicraft Making'], []);

        $this->opportunity($cafe, OpportunityCategory::Job, 'Barista (Part-time)', [
            'description' => 'Prepare coffee drinks and serve customers at our café. Training is provided.',
            'location_text' => 'Session Road, Baguio City',
            'min_education' => EducationLevel::SeniorHigh,
            'slots' => 3,
        ], ['employment_type' => EmploymentType::PartTime, 'salary_min' => 9000, 'work_setup' => WorkSetup::Onsite, 'min_experience_years' => 0],
            ['Customer Service', 'Food and Beverage Services', 'Cashiering'], ['Resume / CV']);

        $this->seeker('demo.seeker@jobauko.test', 'Juan Bayawon (Demo)', Classification::FreshGraduate, EducationLevel::CollegeGraduate, 'Poblacion',
            'BS Information Technology', ['Networking', 'Technical Support', 'Microsoft Office', 'Customer Service'], [[InterestCategory::Job, 'Information Technology']]);

        $this->seeker('demo.student@jobauko.test', 'Liza Pagaduan (Demo)', Classification::Student, EducationLevel::SeniorHigh, 'Abatan',
            'BS Agriculture', ['Organic Farming', 'Teamwork'], [[InterestCategory::Scholarship, 'Agriculture'], [InterestCategory::Livelihood, 'Farming']]);

        MatchRefresher::invalidateAll();

        $this->command?->info('Demo data ready. Log in as demo.seeker@jobauko.test, demo.student@jobauko.test or demo.org@jobauko.test (password: password).');
    }

    private function organization(string $name, OrganizationType $type, ?string $barangay, string $description): Organization
    {
        $organization = Organization::firstOrNew(['name' => $name]);
        $organization->fill([
            'type' => $type,
            'official_email' => Str::slug($name).'@example.com',
            'contact_number' => '0917 000 0000',
            'office_address' => $barangay ? "Brgy. {$barangay}, Bauko, Mountain Province" : 'Baguio City',
            'barangay_id' => $barangay ? Barangay::where('name', $barangay)->value('id') : null,
            'description' => $description,
            'representative_name' => 'Demo Representative',
        ]);
        $organization->slug ??= Str::slug($name);
        $organization->verification_status = VerificationStatus::Verified;
        $organization->verified_at ??= now();
        $organization->save();

        return $organization;
    }

    private function member(Organization $organization, string $email, string $name): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => 'password']);
        $user->account_type = AccountType::Organization;
        $user->email_verified_at ??= now();
        $user->save();

        $organization->members()->updateOrCreate(['user_id' => $user->id], ['role' => OrganizationRole::Admin, 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $details
     * @param  list<string>  $skills
     * @param  list<string>  $documents
     */
    private function opportunity(Organization $organization, OpportunityCategory $category, string $title, array $attributes, array $details, array $skills, array $documents): void
    {
        $opportunity = Opportunity::firstOrNew(['organization_id' => $organization->id, 'title' => $title]);
        $opportunity->fill([
            'application_deadline' => now()->addWeeks(rand(2, 8))->toDateString(),
            'contact_email' => $organization->official_email,
            ...$attributes,
        ]);
        $opportunity->category = $category;
        $opportunity->slug ??= Str::slug($title).'-demo';
        $opportunity->status = OpportunityStatus::Published;
        $opportunity->published_at ??= now()->subDays(rand(0, 10));
        $opportunity->save();

        $relation = match ($category) {
            OpportunityCategory::Job => 'jobDetail',
            OpportunityCategory::Scholarship => 'scholarshipDetail',
            OpportunityCategory::Livelihood => 'livelihoodDetail',
        };
        $opportunity->{$relation}()->updateOrCreate([], $details);

        $opportunity->skills()->sync(collect($skills)->mapWithKeys(fn ($name) => [Skill::findOrCreateByName($name)->id => ['is_required' => true]])->all());

        if ($opportunity->requiredDocuments()->doesntExist()) {
            foreach ($documents as $document) {
                $opportunity->requiredDocuments()->create(['name' => $document, 'is_required' => true]);
            }
        }
    }

    /**
     * @param  list<string>  $skills
     * @param  list<array{0: InterestCategory, 1: string}>  $interests
     */
    private function seeker(string $email, string $name, Classification $classification, EducationLevel $education, string $barangay, string $course, array $skills, array $interests): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $name, 'password' => 'password', 'barangay_id' => Barangay::where('name', $barangay)->value('id')]);
        $user->account_type = AccountType::Seeker;
        $user->email_verified_at ??= now();
        $user->save();

        $profile = $user->applicantProfile()->updateOrCreate([], ['classification' => $classification, 'highest_education' => $education]);
        $profile->education()->firstOrCreate(['course' => $course], ['level' => $education === EducationLevel::SeniorHigh ? EducationLevel::CollegeLevel : $education, 'school' => 'Mountain Province State Polytechnic College', 'is_current' => $classification === Classification::Student]);
        $profile->skills()->syncWithoutDetaching(collect($skills)->mapWithKeys(fn ($s) => [Skill::findOrCreateByName($s)->id => ['source' => 'manual']])->all());

        foreach ($interests as [$category, $field]) {
            $profile->interests()->firstOrCreate(['category' => $category, 'field' => $field]);
        }

        if ($user->notificationPreferences()->doesntExist()) {
            app(NotificationPreferenceService::class)->applyClassificationDefaults($user, $classification);
        }
    }
}
