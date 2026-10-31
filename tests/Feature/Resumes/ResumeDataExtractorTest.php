<?php

namespace Tests\Feature\Resumes;

use App\Services\Resumes\ResumeDataExtractor;
use Database\Seeders\SkillSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResumeDataExtractorTest extends TestCase
{
    use RefreshDatabase;

    private const SAMPLE = <<<'TEXT'
        JUAN D. CRUZ
        Poblacion, Bauko, Mountain Province
        juan.cruz@example.com | 0917 123 4567

        OBJECTIVE
        To obtain a position where I can use my skills in web development and customer service.

        EDUCATION
        Bachelor of Science in Information Technology
        Mountain Province State Polytechnic College
        2018 - 2022
        Bauko National High School
        2012 - 2018

        WORK EXPERIENCE
        IT Support Assistant | Municipal Hall of Bauko
        June 2022 - Present
        • Maintained computers and printers for municipal offices
        • Set up the office network and handled troubleshooting requests from staff
        Sales Clerk - Bauko General Merchandise
        2019 - 2021

        SKILLS
        PHP, Laravel, MySQL, Customer Service
        Computer Hardware Servicing, Teamwork

        CERTIFICATIONS
        Computer Systems Servicing NC II (2021)
        Civil Service Professional Eligibility

        TRAININGS AND SEMINARS
        Basic Web Development Workshop - DICT (40 hours)
        Financial Literacy Seminar - DTI

        REFERENCES
        Available upon request
        TEXT;

    private function extract(): array
    {
        $this->seed(SkillSeeder::class);

        return app(ResumeDataExtractor::class)->extract(self::SAMPLE);
    }

    public function test_it_finds_dictionary_and_listed_skills()
    {
        $skills = $this->extract()['skills'];

        foreach (['PHP', 'Laravel', 'MySQL', 'Customer Service', 'Computer Hardware Servicing', 'Teamwork', 'Web Development'] as $skill) {
            $this->assertContains($skill, $skills);
        }
    }

    public function test_it_groups_education_entries()
    {
        $education = $this->extract()['education'];

        $this->assertCount(2, $education);

        $this->assertSame('college_graduate', $education[0]['level']);
        $this->assertSame('Bachelor of Science in Information Technology', $education[0]['course']);
        $this->assertSame('Mountain Province State Polytechnic College', $education[0]['school']);
        $this->assertSame(2022, $education[0]['year_graduated']);

        $this->assertSame('junior_high', $education[1]['level']);
        $this->assertSame('Bauko National High School', $education[1]['school']);
        $this->assertSame(2018, $education[1]['year_graduated']);
    }

    public function test_it_parses_work_experience_with_dates()
    {
        $experience = $this->extract()['experience'];

        $this->assertCount(2, $experience);

        $this->assertSame('IT Support Assistant', $experience[0]['position']);
        $this->assertSame('Municipal Hall of Bauko', $experience[0]['company']);
        $this->assertSame('2022-06-01', $experience[0]['start_date']);
        $this->assertNull($experience[0]['end_date']);
        $this->assertStringContainsString('Maintained computers', $experience[0]['description']);

        $this->assertSame('Sales Clerk', $experience[1]['position']);
        $this->assertSame('Bauko General Merchandise', $experience[1]['company']);
        $this->assertSame('2019-01-01', $experience[1]['start_date']);
        $this->assertSame('2021-01-01', $experience[1]['end_date']);
    }

    public function test_it_finds_certifications_with_issuers()
    {
        $certifications = collect($this->extract()['certifications'])->keyBy('name');

        $this->assertSame('TESDA', $certifications['Computer Systems Servicing NC II']['issuer']);
        $this->assertSame('Civil Service Commission', $certifications['Civil Service Professional Eligibility']['issuer']);
    }

    public function test_it_finds_trainings_with_provider_and_hours()
    {
        $trainings = $this->extract()['trainings'];

        $this->assertSame(['title' => 'Basic Web Development Workshop', 'provider' => 'DICT', 'hours' => 40], $trainings[0]);
        $this->assertSame('Financial Literacy Seminar', $trainings[1]['title']);
        $this->assertSame('DTI', $trainings[1]['provider']);
    }

    public function test_it_returns_empty_results_for_unstructured_text()
    {
        $this->seed(SkillSeeder::class);

        $result = app(ResumeDataExtractor::class)->extract('Hello, this document has no resume content at all.');

        $this->assertSame([], $result['education']);
        $this->assertSame([], $result['experience']);
        $this->assertSame([], $result['trainings']);
    }
}
