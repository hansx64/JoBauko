<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\AssistanceType;
use App\Enums\Classification;
use App\Enums\EducationLevel;
use App\Enums\EmploymentType;
use App\Enums\OpportunityCategory;
use App\Enums\OpportunityStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\TrainingType;
use App\Enums\VerificationStatus;
use App\Enums\WorkSetup;
use App\Models\Barangay;
use App\Models\Opportunity;
use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;
use App\Services\Recommendation\MatchRefresher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Realistic-looking organizations and opportunities (three of each kind) so
 * the portal does not look empty. The organizations and their contact
 * details are made up. Safe to run more than once.
 *
 *   php artisan db:seed --class=SampleDataSeeder
 *
 * Opportunities are saved directly, not through OpportunityService, so no
 * alerts are sent to seekers.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([LocationSeeder::class, SkillSeeder::class]);

        $agriculture = $this->organization('Bauko Municipal Agriculture Office', OrganizationType::Lgu, 'Poblacion', [
            'official_email' => 'agriculture.bauko@gmail.com',
            'contact_number' => '0919 482 3170',
            'office_address' => 'Municipal Hall Compound, Poblacion, Bauko, Mountain Province',
            'description' => 'Provides farm extension services, livelihood assistance and agricultural training to farmers and residents of Bauko.',
            'representative_name' => 'Evelyn B. Lagman',
            'representative_position' => 'Municipal Agriculturist',
        ]);

        $coop = $this->organization('Abatan Valley Multi-Purpose Cooperative', OrganizationType::Ngo, 'Abatan', [
            'official_email' => 'abatanvalleympc@gmail.com',
            'contact_number' => '0927 615 0842',
            'office_address' => 'Halsema Highway, Abatan, Bauko, Mountain Province',
            'description' => 'A farmers’ cooperative serving vegetable growers in Abatan and nearby barangays with savings, credit, farm supplies and marketing support since 2004.',
            'representative_name' => 'Rodel P. Kitongan',
            'representative_position' => 'General Manager',
        ]);

        $inn = $this->organization('Pinewood Ridge Inn', OrganizationType::PrivateCompany, 'Mount Data', [
            'official_email' => 'hr.pinewoodridgeinn@gmail.com',
            'contact_number' => '0917 803 2264',
            'office_address' => 'Km. 99 Halsema Highway, Mount Data, Bauko, Mountain Province',
            'description' => 'A 32-room mountain lodge and restaurant along Halsema Highway, serving travellers between Baguio and Sagada.',
            'representative_name' => 'Marites C. Daoas',
            'representative_position' => 'Human Resources Officer',
        ]);

        $foundation = $this->organization('Cordillera Highlands Education Foundation', OrganizationType::Ngo, 'Bagnen Proper', [
            'official_email' => 'scholarships.chef@gmail.com',
            'contact_number' => '0998 271 5536',
            'office_address' => 'Bagnen Proper, Bauko, Mountain Province',
            'description' => 'A non-profit foundation that helps young people from Mountain Province finish school through scholarships, mentoring and career guidance.',
            'representative_name' => 'Fr. Nestor A. Bilagot',
            'representative_position' => 'Executive Director',
        ]);

        // Jobs
        $this->opportunity($inn, OpportunityCategory::Job, 'Front Desk Associate', [
            'description' => "Welcome guests, handle check-ins and check-outs, take reservations by phone and online, and answer questions about rooms and tours around Bauko and Sagada.\n\nYou will work rotating shifts with a team of four.",
            'qualifications' => "At least senior high school graduate; a hospitality or tourism course is an advantage.\nClear spoken and written English and Filipino.\nComfortable using a computer and a booking system.",
            'benefits' => 'SSS, PhilHealth and Pag-IBIG, free staff meals, service charge share, and staff housing for those living far from Mount Data.',
            'application_instructions' => 'Apply through JoBauko. Shortlisted applicants will be called for an interview at the inn.',
            'min_education' => EducationLevel::SeniorHigh,
            'barangay_id' => $this->barangay('Mount Data'),
            'slots' => 2,
        ], ['employment_type' => EmploymentType::FullTime, 'salary_min' => 13500, 'salary_max' => 15000, 'work_setup' => WorkSetup::Onsite, 'min_experience_years' => 0],
            ['Customer Service', 'Microsoft Office', 'Communication Skills'], ['Barangay Clearance']);

        $this->opportunity($coop, OpportunityCategory::Job, 'Bookkeeper', [
            'description' => 'Record daily transactions, prepare monthly financial statements and loan schedules, and assist during the annual audit by the Cooperative Development Authority.',
            'qualifications' => "Graduate of BS Accountancy, BS Business Administration major in Financial Management, or a related course.\nAt least one year of bookkeeping experience, preferably in a cooperative.\nKnowledge of spreadsheets and basic accounting software.",
            'benefits' => 'Mandatory benefits, 13th month pay, rice allowance and patronage refund once a member.',
            'min_education' => EducationLevel::CollegeGraduate,
            'barangay_id' => $this->barangay('Abatan'),
            'slots' => 1,
        ], ['employment_type' => EmploymentType::FullTime, 'salary_min' => 16000, 'salary_max' => 19000, 'work_setup' => WorkSetup::Onsite, 'min_experience_years' => 1],
            ['Bookkeeping', 'Microsoft Office', 'Data Encoding'], ['Transcript of Records', 'Certificate of Employment']);

        $this->opportunity($agriculture, OpportunityCategory::Job, 'Agricultural Technician (Job Order)', [
            'description' => 'Visit farms in assigned barangays, record planting and harvest data, help farmers register in the RSBSA, and assist in distributing seeds and fertilizer.',
            'qualifications' => "Graduate of BS Agriculture or a two-year agricultural course.\nWilling to do field work in all barangays of Bauko.\nCan ride a motorcycle (preferred).",
            'benefits' => 'Job order contract for six months, renewable.',
            'min_education' => EducationLevel::Vocational,
            'barangay_id' => $this->barangay('Poblacion'),
            'slots' => 3,
        ], ['employment_type' => EmploymentType::JobOrder, 'salary_min' => 14000, 'work_setup' => WorkSetup::Onsite, 'min_experience_years' => 0],
            ['Crop Production', 'Highland Vegetable Farming', 'Data Encoding'], ['Transcript of Records', 'Barangay Clearance']);

        // Scholarships
        $this->opportunity($foundation, OpportunityCategory::Scholarship, 'Highlands College Scholarship Grant', [
            'description' => 'A four-year scholarship for incoming college freshmen from Mountain Province who show strong academic performance but need financial help.',
            'qualifications' => 'Incoming first-year college student; resident of Mountain Province; not a recipient of another full scholarship.',
            'benefits' => 'Full tuition and miscellaneous fees, ₱3,000 monthly allowance and ₱5,000 book allowance every semester.',
            'application_instructions' => 'Qualified applicants will take a written exam and attend a family interview.',
            'min_education' => EducationLevel::SeniorHigh,
            'barangay_id' => $this->barangay('Bagnen Proper'),
            'slots' => 15,
        ], ['education_level' => EducationLevel::CollegeLevel, 'eligible_courses' => ['BS Education', 'BS Nursing', 'BS Civil Engineering', 'BS Agriculture', 'BS Information Technology'], 'academic_requirements' => 'General weighted average of at least 88% in Grade 12, with no grade below 83%.', 'financial_requirements' => 'Combined annual family income of not more than ₱300,000.'],
            [], ['Report Card (Form 138)', 'Certificate of Good Moral Character', 'Income Tax Return or Certificate of Tax Exemption of Parents']);

        $this->opportunity($agriculture, OpportunityCategory::Scholarship, 'Municipal Agriculture Scholarship Program', [
            'description' => 'Supports Bauko students taking up agriculture-related courses, who will serve as farm extension volunteers in their barangays during summer breaks.',
            'qualifications' => 'Bona fide resident of Bauko for at least three years; enrolled or incoming student in an agriculture-related course.',
            'benefits' => '₱10,000 per semester for school fees and a ₱1,500 monthly allowance.',
            'min_education' => EducationLevel::SeniorHigh,
            'barangay_id' => $this->barangay('Poblacion'),
            'slots' => 20,
        ], ['education_level' => EducationLevel::CollegeLevel, 'eligible_courses' => ['BS Agriculture', 'BS Agricultural and Biosystems Engineering', 'BS Forestry', 'BS Agribusiness'], 'academic_requirements' => 'No failing grades in the previous semester.', 'financial_requirements' => 'Child of a farmer registered in the RSBSA.'],
            [], ['Certificate of Enrollment', 'Certificate of Grades', 'Barangay Certificate of Residency']);

        $this->opportunity($foundation, OpportunityCategory::Scholarship, 'Senior High School Educational Assistance', [
            'description' => 'One-time educational assistance for Grade 11 and Grade 12 students from low-income families, to help with school supplies, uniforms and transportation.',
            'qualifications' => 'Currently enrolled in Grade 11 or 12 in any school in Bauko.',
            'benefits' => '₱6,000 cash assistance for the school year.',
            'min_education' => EducationLevel::JuniorHigh,
            'barangay_id' => $this->barangay('Bagnen Proper'),
            'slots' => 40,
        ], ['education_level' => EducationLevel::SeniorHigh, 'eligible_courses' => [], 'academic_requirements' => 'General average of at least 82% in the last school year.', 'financial_requirements' => 'Family is a 4Ps beneficiary or has a certificate of indigency.'],
            [], ['Certificate of Enrollment', 'Certificate of Indigency']);

        // Livelihood programs
        $this->opportunity($agriculture, OpportunityCategory::Livelihood, 'Swine Dispersal Program', [
            'description' => 'Qualified families receive two weaned piglets with starter feeds and veterinary support. Beneficiaries return two piglets from the first litter so the program can reach more families.',
            'qualifications' => 'Resident of Bauko with space for a pigpen at least 20 meters from the nearest house.',
            'min_education' => null,
            'barangay_id' => $this->barangay('Poblacion'),
            'slots' => 25,
        ], ['assistance_type' => AssistanceType::Livestock, 'target_beneficiaries' => 'Low-income households, solo parents and senior citizens who can care for livestock.', 'assistance_provided' => 'Two weaned piglets, three sacks of starter feed, vaccination and deworming.', 'training_requirements' => 'Attend a one-day seminar on swine raising and biosecurity.', 'target_classifications' => [Classification::Unemployed->value, Classification::SelfEmployed->value]],
            ['Livestock Raising'], ['Barangay Certificate of Residency', 'Certificate of Indigency']);

        $this->opportunity($coop, OpportunityCategory::Livelihood, 'Farm Production Loan', [
            'description' => 'Low-interest loans for vegetable growers to buy seeds, fertilizer and greenhouse materials, payable after harvest.',
            'qualifications' => 'Member of the cooperative in good standing, or willing to become a member.',
            'min_education' => null,
            'barangay_id' => $this->barangay('Abatan'),
            'slots' => 60,
        ], ['assistance_type' => AssistanceType::Loan, 'target_beneficiaries' => 'Small vegetable farmers in Abatan, Lagawa, Sinto and nearby barangays.', 'assistance_provided' => 'Loans of ₱10,000 to ₱50,000 at 1% interest a month, payable within six months.', 'training_requirements' => 'Attend the Pre-Membership Education Seminar.', 'target_classifications' => [Classification::SelfEmployed->value, Classification::Unemployed->value]],
            ['Highland Vegetable Farming', 'Crop Production'], ['Valid Government ID', 'Barangay Clearance']);

        $this->opportunity($coop, OpportunityCategory::Livelihood, 'Food Processing Starter Kit', [
            'description' => 'Starter kits for residents who want to start a small food business making strawberry jam, pickled vegetables or rice cakes, with help in packaging and selling through the cooperative store.',
            'qualifications' => 'Resident of Bauko, 18 years old and above.',
            'min_education' => null,
            'barangay_id' => $this->barangay('Abatan'),
            'slots' => 15,
        ], ['assistance_type' => AssistanceType::StarterKit, 'target_beneficiaries' => 'Women, out-of-school youth and unemployed residents.', 'assistance_provided' => 'Cooking equipment, jars and packaging materials worth ₱8,000.', 'training_requirements' => 'Complete the three-day food processing and food safety training.', 'target_classifications' => [Classification::Unemployed->value, Classification::OutOfSchoolYouth->value]],
            ['Food Processing'], ['Barangay Certificate of Residency']);

        // Seminars and trainings
        $this->opportunity($agriculture, OpportunityCategory::Training, 'Organic Vegetable Production Seminar', [
            'description' => 'A two-day seminar on growing highland vegetables with fewer chemicals: soil health, composting, natural pest control and getting organic certification.',
            'barangay_id' => $this->barangay('Poblacion'),
            'location_text' => 'Municipal Session Hall, Poblacion',
            'slots' => 50,
        ], [
            'training_type' => TrainingType::Seminar,
            'starts_on' => now()->addWeeks(3)->startOfWeek()->addDay()->toDateString(),
            'ends_on' => now()->addWeeks(3)->startOfWeek()->addDays(2)->toDateString(),
            'schedule' => '8:00 AM – 5:00 PM',
            'duration_hours' => 16,
            'certificate' => 'Certificate of attendance',
            'topics' => "Soil testing and soil health\nMaking compost and vermicast\nNatural pest and disease control\nSteps to organic certification",
            'inclusions' => 'Lunch, snacks and a starter pack of vermicast.',
            'target_participants' => 'Vegetable farmers and agriculture students.',
            'target_classifications' => [Classification::SelfEmployed->value, Classification::Student->value],
        ], ['Organic Farming', 'Highland Vegetable Farming'], []);

        $this->opportunity($inn, OpportunityCategory::Training, 'Housekeeping Skills Training', [
            'description' => 'A hands-on training on hotel housekeeping held at the inn. Outstanding trainees may be offered work at Pinewood Ridge Inn.',
            'barangay_id' => $this->barangay('Mount Data'),
            'slots' => 20,
        ], [
            'training_type' => TrainingType::SkillsTraining,
            'starts_on' => now()->addWeeks(5)->startOfWeek()->toDateString(),
            'ends_on' => now()->addWeeks(7)->startOfWeek()->addDays(4)->toDateString(),
            'schedule' => 'Monday to Friday, 8:00 AM – 4:00 PM',
            'duration_hours' => 120,
            'fee' => 0,
            'certificate' => 'Certificate of completion',
            'topics' => "Preparing and cleaning guest rooms\nLaundry and linen handling\nCleaning public areas\nGuest service and safety",
            'inclusions' => 'Free lunch, uniform and training materials.',
            'target_participants' => 'Out-of-school youth and unemployed residents aged 18 to 35.',
            'target_classifications' => [Classification::OutOfSchoolYouth->value, Classification::Unemployed->value],
        ], ['Housekeeping', 'Customer Service'], []);

        $this->opportunity($foundation, OpportunityCategory::Training, 'Career Readiness Workshop for Graduating Students', [
            'description' => 'A one-day workshop to help graduating students prepare for their first job: writing a resume, answering interview questions and finding work online safely.',
            'barangay_id' => $this->barangay('Bagnen Proper'),
            'location_text' => 'Bagnen Barangay Hall',
            'slots' => 60,
        ], [
            'training_type' => TrainingType::Workshop,
            'starts_on' => now()->addWeeks(2)->startOfWeek()->addDays(5)->toDateString(),
            'ends_on' => now()->addWeeks(2)->startOfWeek()->addDays(5)->toDateString(),
            'schedule' => 'Saturday, 9:00 AM – 4:00 PM',
            'duration_hours' => 7,
            'certificate' => 'Certificate of participation',
            'topics' => "Writing a resume and cover letter\nMock job interviews\nAvoiding online job scams\nPreparing government requirements",
            'inclusions' => 'Lunch, snacks and a printed copy of your resume.',
            'target_participants' => 'Graduating senior high and college students.',
            'target_classifications' => [Classification::Student->value, Classification::FreshGraduate->value],
        ], ['Communication Skills'], []);

        // .test addresses so notifications never reach a real inbox.
        $this->member($agriculture, 'agriculture@jobauko.test', 'Evelyn Lagman');
        $this->member($coop, 'abatanvalley@jobauko.test', 'Rodel Kitongan');
        $this->member($inn, 'pinewoodridge@jobauko.test', 'Marites Daoas');
        $this->member($foundation, 'highlandsfoundation@jobauko.test', 'Nestor Bilagot');

        MatchRefresher::invalidateAll();

        $this->command?->info('Sample organizations and opportunities are ready.');
    }

    /**
     * An administrator login for the organization. The password is random and
     * shown once, so it is never stored in the code.
     */
    private function member(Organization $organization, string $email, string $name): void
    {
        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $password = Str::password(14, symbols: false);
            $user->fill(['name' => $name, 'password' => $password]);
            $user->account_type = AccountType::Organization;
            $user->email_verified_at = now();
            $user->save();

            $this->command?->line("  {$organization->name}: {$email} / {$password}");
        }

        $organization->members()->updateOrCreate(['user_id' => $user->id], ['role' => OrganizationRole::Admin, 'is_active' => true]);
    }

    private function barangay(string $name): ?int
    {
        return Barangay::where('name', $name)->value('id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function organization(string $name, OrganizationType $type, string $barangay, array $attributes): Organization
    {
        $organization = Organization::firstOrNew(['name' => $name]);
        $organization->fill(['type' => $type, 'barangay_id' => $this->barangay($barangay), ...$attributes]);
        $organization->slug ??= Str::slug($name);
        $organization->verification_status = VerificationStatus::Verified;
        $organization->verified_at ??= now()->subDays(rand(20, 60));
        $organization->save();

        return $organization;
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
            'application_deadline' => now()->addWeeks(rand(3, 8))->toDateString(),
            'contact_email' => $organization->official_email,
            'contact_number' => $organization->contact_number,
            ...$attributes,
        ]);
        $opportunity->category = $category;
        $opportunity->slug ??= Str::slug($title);
        $opportunity->status = OpportunityStatus::Published;
        $opportunity->published_at ??= now()->subDays(rand(1, 14));
        $opportunity->save();

        $relation = match ($category) {
            OpportunityCategory::Job => 'jobDetail',
            OpportunityCategory::Scholarship => 'scholarshipDetail',
            OpportunityCategory::Livelihood => 'livelihoodDetail',
            OpportunityCategory::Training => 'trainingDetail',
        };
        $opportunity->{$relation}()->updateOrCreate([], $details);

        $opportunity->skills()->sync(collect($skills)->mapWithKeys(fn ($name) => [Skill::findOrCreateByName($name)->id => ['is_required' => true]])->all());

        if ($opportunity->requiredDocuments()->doesntExist()) {
            foreach ($documents as $document) {
                $opportunity->requiredDocuments()->create(['name' => $document, 'is_required' => true]);
            }
        }
    }
}
